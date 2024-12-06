<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseMigrationController extends Controller
{
    public function fetchData(Request $request)
    {
        $request->validate([
            'source_database' => 'required|string',
            'source_table' => 'required|string',
            'target_database' => 'required|string',
            'target_table' => 'required|string',
        ]);

        // Configure connection to source database
        config(['database.connections.source' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => $request->source_database,
            'username' => 'root',
            'password' => '1234',
        ]]);

        // Get source data and columns
        $sourceData = DB::connection('source')->table($request->source_table)->get();
        $columns = Schema::connection('source')->getColumnListing($request->source_table);

        // Get foreign key relationships
        $foreignKeys = $this->getForeignKeyRelationships($request->source_database, $request->source_table);

        return view('welcome', [
            'sourceData' => $sourceData,
            'columns' => $columns,
            'sourceDatabase' => $request->source_database,
            'sourceTable' => $request->source_table,
            'targetDatabase' => $request->target_database,
            'targetTable' => $request->target_table,
            'foreignKeys' => $foreignKeys
        ]);
    }

    private function getForeignKeyRelationships($database, $table)
    {
        $query = "
            SELECT 
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as referenced_table,
                REFERENCED_COLUMN_NAME as referenced_column
            FROM
                information_schema.KEY_COLUMN_USAGE
            WHERE
                TABLE_SCHEMA = ? AND
                TABLE_NAME = ? AND
                REFERENCED_TABLE_NAME IS NOT NULL
        ";

        $foreignKeys = DB::connection('source')
            ->select($query, [$database, $table]);

        // Configure target database connection
        config(['database.connections.target' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => request()->target_database,
            'username' => 'root',
            'password' => '1234',
        ]]);

        DB::purge('target');

        foreach ($foreignKeys as $fk) {
            try {
                if (!Schema::connection('target')->hasTable($fk->referenced_table)) {
                    $fk->has_data = false;
                    $fk->target_columns = [];
                    continue;
                }

                // Get source table columns
                $sourceColumns = Schema::connection('source')
                    ->getColumnListing($fk->referenced_table);
                
                // Get target table columns
                $targetColumns = Schema::connection('target')
                    ->getColumnListing($fk->referenced_table);

                $fk->source_columns = $sourceColumns;
                $fk->target_columns = $targetColumns;
                
                $count = DB::connection('target')
                    ->table($fk->referenced_table)
                    ->count();
                $fk->has_data = $count > 0;
            } catch (\Exception $e) {
                $fk->has_data = false;
                $fk->target_columns = [];
            }
        }

        return $foreignKeys;
    }

    public function migrateData(Request $request)
    {
        $request->validate([
            'source_database' => 'required|string',
            'source_table' => 'required|string',
            'target_database' => 'required|string',
            'target_table' => 'required|string',
            'selected_columns' => 'required|array',
            'ignore_values' => 'array',
            'column_mapping' => 'array',
        ]);

        // Configure connections
        config(['database.connections.source' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => $request->source_database,
            'username' => 'root',
            'password' => '1234',
        ]]);

        config(['database.connections.target' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => $request->target_database,
            'username' => 'root',
            'password' => '1234',
        ]]);

        // Check foreign key dependencies
        $foreignKeys = $this->getForeignKeyRelationships($request->source_database, $request->source_table);
        $missingTables = [];

        foreach ($foreignKeys as $fk) {
            $tableExists = Schema::connection('target')->hasTable($fk->referenced_table);
            if (!$tableExists) {
                $missingTables[] = $fk->referenced_table;
            }
        }

        if (!empty($missingTables)) {
            return back()->withErrors([
                'foreign_keys' => 'The following required tables are missing in the target database: ' . 
                                implode(', ', array_unique($missingTables))
            ])->withInput();
        }

        // Get target table columns
        $targetColumns = Schema::connection('target')->getColumnListing($request->target_table);
        
        // Process column mapping
        $columnMapping = $request->column_mapping;
        $validColumns = [];
        $selectColumns = [];

        foreach ($request->selected_columns as $sourceColumn) {
            $targetColumn = isset($columnMapping[$sourceColumn]) && !empty($columnMapping[$sourceColumn]) 
                ? $columnMapping[$sourceColumn] 
                : $sourceColumn;

            if (in_array($targetColumn, $targetColumns)) {
                $validColumns[] = $sourceColumn;
                // If column names are different, use alias in select
                if ($sourceColumn !== $targetColumn) {
                    $selectColumns[] = DB::raw("`$sourceColumn` as `$targetColumn`");
                } else {
                    $selectColumns[] = $sourceColumn;
                }
            }
        }

        // Process ignore values
        $ignoreValues = [];
        if ($request->has('ignore_values')) {
            foreach ($request->ignore_values as $column => $values) {
                if (!empty($values)) {
                    $ignoreValues[$column] = array_map('trim', explode(',', $values));
                }
            }
        }

        // Get source data with ignore values filter
        $query = DB::connection('source')->table($request->source_table)->select($selectColumns);
        
        // Apply ignore filters
        foreach ($ignoreValues as $column => $values) {
            if (in_array($column, $validColumns)) {
                $query->whereNotIn($column, $values);
            }
        }
        
        $sourceData = $query->get();

        // Insert data into target table
        foreach ($sourceData as $row) {
            $data = (array) $row;
            
            // Check if record already exists (assuming first mapped column is unique identifier)
            $firstColumn = array_key_first($data);
            $exists = DB::connection('target')
                ->table($request->target_table)
                ->where($firstColumn, $data[$firstColumn])
                ->exists();
            
            if (!$exists) {
                DB::connection('target')
                    ->table($request->target_table)
                    ->insert($data);
            }
        }

        // Get migrated data
        $migratedData = DB::connection('target')
            ->table($request->target_table)
            ->get();

        return view('welcome', [
            'migratedData' => $migratedData,
            'migratedColumns' => $targetColumns,
        ]);
    }
} 