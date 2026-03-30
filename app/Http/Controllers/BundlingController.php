<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bundling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BundlingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bundlings = Bundling::with('materials.product')->get();

        return response()->json([
            'bundlings' => $bundlings
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'materials' => 'required|array',
            'materials.*.product_id' => 'required_with:materials|exists:products,id',
            'materials.*.quantity' => 'required_with:materials|integer|min:1',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('bundlings', 'public');
        }

        $bundling = Bundling::create($validated);

        foreach ($validated['materials'] as $material) {
            $bundling->materials()->create([
                'product_id' => $material['product_id'],
                'quantity' => $material['quantity'],
            ]);
        }

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Created a new bundling',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'Bundling created successfully',
            'bundling' => $bundling->load('materials.product')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Bundling $bundling, $id)
    {
        $bundling = Bundling::find($id);

        if (!$bundling) {
            return response()->json([
                'message' => 'Bundling not found'
            ], 404);
        }

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed a bundling',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'bundling' => $bundling->load('materials')
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bundling $bundling)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $bundling = Bundling::find($id);

        if (!$bundling) {
            return response()->json(['message' => 'Bundling not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'materials' => 'required|array',
            'materials.*.product_id' => 'required_with:materials|exists:products,id',
            'materials.*.quantity' => 'required_with:materials|integer|min:1',
        ]);

        if ($request->hasFile('image')) {

            if ($bundling->image && Storage::disk('public')->exists($bundling->image)) {
                Storage::disk('public')->delete($bundling->image);
            }

            $validated['image'] = $request->file('image')->store('bundlings', 'public');
        }

        $bundling->update($validated);

        $bundling->materials()->delete();

        foreach ($validated['materials'] as $material) {
            $bundling->materials()->create([
                'product_id' => $material['product_id'],
                'quantity' => $material['quantity'],
            ]);
        }

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Updated a bundling',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'Bundling updated successfully',
            'bundling' => $bundling->load('materials.product')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bundling $bundling, $id)
    {
        $bundling = Bundling::find($id);

        if (!$bundling) {
            return response()->json(
                [
                    'message' => 'Bundling not found'
                ],
                404
            );
        }

        $bundling->delete();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Deleted a bundling',
            'activity_type' => 'crud'
        ]);

        return response()->json(
            [
                'message' => 'Bundling deleted successfully'
            ],
            200
        );
    }
}
