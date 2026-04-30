<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Feature;

class FeatureController extends Controller
{
    /** GET /api/features — list all features for the matrix */
    public function index()
    {
        $features = Feature::orderBy('sortOrder')->orderBy('label')->get();
        return ApiResponse::success('Features loaded', ['features' => $features]);
    }
}