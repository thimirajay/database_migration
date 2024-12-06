<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    // ... existing code ...

    public function register(): void
    {
        $this->renderable(function (MethodNotAllowedHttpException $e) {
            return redirect()
                ->route('home')
                ->withErrors(['error' => 'Invalid request method. Please submit the form properly.']);
        });

        $this->renderable(function (Throwable $e) {
            if ($e instanceof \Exception) {
                return redirect()
                    ->route('home')
                    ->withErrors(['error' => $e->getMessage()]);
            }
        });
    }
} 