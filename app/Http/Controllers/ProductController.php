<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::with('category')->latest()->get();

        return response()->json([
            'products' => $products
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
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        }

        $product = Product::create($validated);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Created a new product',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $product = Product::with('category')
            ->withSum(['transactionMaterials as total_quantity' => function($query) {
                $query->join('transactions', 'transaction_materials.transaction_id', '=', 'transactions.id')
                      ->whereIn('transactions.status', ['completed', 'accepted']);
            }], 'quantity')
            ->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->total_revenue = ($product->total_quantity ?? 0) * $product->price;

        return response()->json([
            'product' => $product
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {

            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        }

        $product->update($validated);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Updated a product',
            'activity_type' => 'crud'
        ]);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(
                [
                    'message' => 'Product not found'
                ],
                404
            );
        }

        $product->delete();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'description' => 'Deleted a product',
            'activity_type' => 'crud'
        ]);


        return response()->json(
            [
                'message' => 'Product deleted successfully'
            ],
            200
        );
    }
}
