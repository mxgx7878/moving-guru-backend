<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends \Illuminate\Foundation\Exceptions\Handler
{
    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            $statusCode = 500;

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'status' => false,
                    'status_code' => 422,
                    'message' => 'Validation failed',
                    'errors' => $exception->errors()
                ], 422);
            }

            if ($exception instanceof AuthenticationException) {
                $statusCode = 401;
            } elseif ($exception instanceof HttpException) {
                $statusCode = $exception->getStatusCode();
            }

            return response()->json([
                'status' => false,
                'status_code' => $statusCode,
                'message' => $exception->getMessage() ?: 'Server Error',
            ], $statusCode);
        }

        return parent::render($request, $exception);
    }
}