<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;

class Handler extends ExceptionHandler
{

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Always return JSON for API requests
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
