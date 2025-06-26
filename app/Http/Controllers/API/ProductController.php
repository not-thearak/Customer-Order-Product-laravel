<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use ErlandMuchasaj\LaravelFileUploader\FileUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index()
    {
         // Retrieve all products
          $products = Product::orderBy('id', 'desc')->get();
        // Append image_url to each product if it's stored locally
        $products->map(function($product) {
            if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
                // If it's a local path (not a full URL), generate a public URL
                $product->image_url = Storage::url($product->image_url);
            }
            return $product;
        });
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

  try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048', // 'image' for file upload
            ]);

            $imagePath = null;
            // Handle image upload
            if ($request->hasFile('image')) {
                // Store the image in the 'public/products' directory
                // and get the relative path
                $imagePath = $request->file('image')->store('products', 'public');
            }

            // Create a new product record
            $product = Product::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'price' => $validatedData['price'],
                'stock' => $validatedData['stock'],
                'image_url' => $imagePath, // Save the path to the database
            ]);

            // Append the full public URL for the response
            if ($product->image_url) {
                $product->image_url = Storage::url($product->image_url);
            }

            return response()->json($product, 201); // 201 Created
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating product: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
         // Find a product by ID
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404); // 404 Not Found
        }

        // Append the full public URL for the response if it's a local path
        if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
            $product->image_url = Storage::url($product->image_url);
        }

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Find the product to update
            $product = Product::find($id);

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            // Validate incoming request data
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|numeric|min:0',
                'stock' => 'sometimes|integer|min:0',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048', // 'image' for file upload
            ]);

            $updateData = $request->only(['name', 'description', 'price', 'stock']);

            // Handle image upload if a new image is provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($product->image_url) {
                    Storage::disk('public')->delete($product->image_url);
                }
                // Store the new image
                $imagePath = $request->file('image')->store('products', 'public');
                $updateData['image_url'] = $imagePath;
            } elseif ($request->has('image_url') && $request->input('image_url') === null) {
                // If 'image_url' is explicitly sent as null, delete the old image
                if ($product->image_url) {
                    Storage::disk('public')->delete($product->image_url);
                }
                $updateData['image_url'] = null;
            }


            // Update the product record
            $product->update($updateData);

            // Append the full public URL for the response
            if ($product->image_url) {
                $product->image_url = Storage::url($product->image_url);
            }

            return response()->json($product); // 200 OK
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating product: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
         // Find the product to delete
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Delete associated image file
        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
        }

        // Delete the product record
        $product->delete();
        return response(['message' => 'Product deleted successfully'], 200); // 204 No Content
    }
}
