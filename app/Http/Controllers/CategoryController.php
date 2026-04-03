<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::withCount('products')->get();

        return response()->json([
            'categories' => $categories
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
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        $category = Category::create($validated);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Created a new category',
            'activity_type' => 'crud'
        ]);

        return response()->json(
            [
                'message' => 'Category created successfully',
                'category' => $category
            ],
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category, $id)
    {
        $category = Category::find($id)->get();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Viewed a category',
            'activity_type' => 'crud'
        ]);

        return response()->json(['category' => $category]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
        ]);

        $category->update($validated);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Updated a category',
            'activity_type' => 'crud'
        ]);

        return response()->json(
            [
                'message' => 'Category updated successfully',
                'category' => $category
            ],
            200
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(
                [
                    'message' => 'Category not found'
                ],
                404
            );
        }

        if ($category->products()->exists()) {
            return response()->json(
                [
                    'message' => 'Tidak bisa menghapus kategori karena masih ada data barang'
                ],
                400
            );
        }

        $category->delete();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Deleted a category',
            'activity_type' => 'crud'
        ]);

        return response()->json(
            [
                'message' => 'Category deleted successfully'
            ],
            200
        );
    }
}
