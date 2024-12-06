<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Database Migration Tool</title>
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Custom styles -->
        <style>
            body {
                background-color: #f8f9fa;
            }
            .form-control:focus {
                border-color: #80bdff;
                box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            }
            .list-group-item {
                padding: 1rem;
            }
            .badge {
                padding: 0.5rem 1rem;
            }
            .btn-warning {
                color: #000;
            }
            .btn-warning:hover {
                color: #000;
                background-color: #ffca2c;
            }
        </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    </head>
    <body>
        <div class="container py-5">
            <h1 class="mb-4 text-center">Database Migration Tool</h1>

            <!-- Database Connection Form -->
            <div class="mb-4 card">
                <div class="card-body">
                    <form id="migrationForm" action="{{ route('fetch.data') }}" method="POST">
                        @csrf
                        <div class="row">
                            <!-- Source Database -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Source Database</h4>
                                <div class="mb-3">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" name="source_database" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Table Name</label>
                                    <input type="text" name="source_table" class="form-control" required>
                                </div>
                            </div>

                            <!-- Target Database -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Target Database</h4>
                                <div class="mb-3">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" name="target_database" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Table Name</label>
                                    <input type="text" name="target_table" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary">
                                Fetch Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Foreign Key Tables Section -->
            @if(isset($foreignKeys) && count($foreignKeys) > 0)
            <div class="mb-4 card">
                <div class="card-body">
                    <h4 class="mb-3">Required Foreign Key Tables</h4>
                    <div class="alert alert-warning">
                        <p class="mb-2">Please ensure the following tables are migrated first:</p>
                        <ul class="list-group">
                            @foreach($foreignKeys as $fk)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Column:</strong> {{ $fk->column_name }} 
                                    <i class="mx-2 fas fa-arrow-right"></i>
                                    <strong>References:</strong> {{ $fk->referenced_table }}.{{ $fk->referenced_column }}
                                </div>
                                <div>
                                    @if(!$fk->has_data)
                                        <form action="{{ route('fetch.data') }}" method="POST" target="_blank" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="source_database" value="{{ $sourceDatabase }}">
                                            <input type="hidden" name="source_table" value="{{ $fk->referenced_table }}">
                                            <input type="hidden" name="target_database" value="{{ $targetDatabase }}">
                                            <input type="hidden" name="target_table" value="{{ $fk->referenced_table }}">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <i class="fas fa-exclamation-triangle"></i> Table Empty - Migrate {{ $fk->referenced_table }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Data Available in Target
                                        </span>
                                    @endif
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            <!-- Source Data Display -->
            @if(isset($sourceData) && isset($columns))
            <div class="mb-4 card">
                <div class="card-body">
                    <h4 class="mb-3">Source Data</h4>
                    <form action="{{ route('migrate.data') }}" method="POST">
                        @csrf
                        <input type="hidden" name="source_database" value="{{ $sourceDatabase }}">
                        <input type="hidden" name="source_table" value="{{ $sourceTable }}">
                        <input type="hidden" name="target_database" value="{{ $targetDatabase }}">
                        <input type="hidden" name="target_table" value="{{ $targetTable }}">

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        @foreach($columns as $column)
                                        <th>
                                            <div class="mb-2">
                                                <input type="text" 
                                                       name="ignore_values[{{ $column }}]" 
                                                       class="form-control form-control-sm" 
                                                       placeholder="Ignore values (e.g., 1,2,3)"
                                                       title="Enter comma-separated values to ignore">
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       name="selected_columns[]" 
                                                       value="{{ $column }}" 
                                                       class="form-check-input" 
                                                       checked 
                                                       id="check_{{ $column }}">
                                                <label class="form-check-label" for="check_{{ $column }}">
                                                    {{ $column }}
                                                </label>
                                            </div>
                                        </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sourceData as $row)
                                    <tr>
                                        @foreach($columns as $column)
                                        <td>{{ $row->$column }}</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 text-center">
                            <button type="submit" class="btn btn-success">
                                Migrate Selected Columns
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <!-- Migrated Data Display -->
            @if(isset($migratedData) && isset($migratedColumns))
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">Migrated Data</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    @foreach($migratedColumns as $column)
                                    <th>{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($migratedData as $row)
                                <tr>
                                    @foreach($migratedColumns as $column)
                                    <td>{{ $row->$column }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Error Messages -->
            @if($errors->any())
            <div class="mt-4 alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
