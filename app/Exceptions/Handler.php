<?php

namespace App\Exceptions;

use Throwable;

class Handler extends \Illuminate\Foundation\Exceptions\Handler
{
    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage()
            ], 500);
        }

        return parent::render($request, $exception);
    }
}