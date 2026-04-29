<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Helpers\ApiResponse;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::active()->orderBy('sortOrder')->get();
        return ApiResponse::success('Plans loaded', ['plans' => $plans]);
    }
}