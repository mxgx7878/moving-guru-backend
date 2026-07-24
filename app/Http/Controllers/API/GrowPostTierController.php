<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GrowPostTier;
use Illuminate\Http\Request;

class GrowPostTierController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => GrowPostTier::active()->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function adminIndex()
    {
        return response()->json([
            'success' => true,
            'data' => GrowPostTier::orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $tier = GrowPostTier::create($this->validated($request));
        return response()->json(['success' => true, 'message' => 'Grow tier created.', 'data' => $tier], 201);
    }

    public function update(Request $request, GrowPostTier $tier)
    {
        $tier->update($this->validated($request, true));
        return response()->json(['success' => true, 'message' => 'Grow tier updated.', 'data' => $tier->fresh()]);
    }

    public function destroy(GrowPostTier $tier)
    {
        $tier->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => 'Grow tier archived.', 'data' => $tier->fresh()]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$required, 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => [$required, 'numeric', 'min:0.5', 'max:99999'],
            'currency' => [$required, 'string', 'size:3'],
            'duration_days' => [$required, 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        return $data;
    }
}
