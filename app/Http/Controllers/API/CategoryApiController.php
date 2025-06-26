<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryApiController extends Controller
{

    public function index()
    {
        return Category::orderBy('id', 'desc')->get();
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = \App\Models\Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    public function show(string $id)
    {
        $categories = Category::orderBy('id','desc')->get();



        if (!$categories) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

          return response([
            'status' => 200,
            'message' => 'categories selected successfully',
            'categories' => $categories
        ]);


    }

    public function update(Request $request, string $id)
    {
        $category = \App\Models\Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }


    public function destroy(string $id)
    {
        $category = \App\Models\Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
