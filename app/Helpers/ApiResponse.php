<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($message, $data = [], $code = 200)
    {
        return response()->json([
            'status' => true,
            'status_code' => $code,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public static function error($message, $errors = [], $code = 400)
    {
        return response()->json([
            'status' => false,
            'status_code' => $code,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}