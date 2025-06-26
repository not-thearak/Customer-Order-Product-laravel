<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retrieve all orders, eager load customer and order items
        $orders = Order::with(['customer', 'orderItems.product'])->get();
        return response()->json($orders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'order_items' => 'required|array',
                'order_items.*.product_id' => 'required|exists:products,id',
                'order_items.*.quantity' => 'required|integer|min:1',
            ]);

            // Start a database transaction
            DB::beginTransaction();

            // Calculate total amount based on products and quantities
            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($validatedData['order_items'] as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                if (!$product) {
                    DB::rollBack();
                    return response()->json(['message' => 'Product with ID ' . $item['product_id'] . ' not found.'], 404);
                }
                if ($product->stock < $item['quantity']) {
                    DB::rollBack();
                    return response()->json(['message' => 'Not enough stock for product: ' . $product->name], 400);
                }
                $totalAmount += $product->price * $item['quantity'];
                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price_at_order' => $product->price, // Store the price at the time of order
                ];

                // Decrease product stock
                $product->decrement('stock', $item['quantity']);
            }

            // Create the order
            $order = Order::create([
                'customer_id' => $validatedData['customer_id'],
                'total_amount' => $totalAmount,
                'status' => 'pending', // Default status
            ]);

            // Attach order items
            foreach ($orderItemsData as $itemData) {
                $order->orderItems()->create($itemData);
            }

            // Commit the transaction
            DB::commit();

            return response()->json($order->load(['customer', 'orderItems.product']), 201); // Return order with relationships
        } catch (ValidationException $e) {
            DB::rollBack(); // Rollback if validation fails
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback on any other exception
            return response()->json(['message' => 'Error creating order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find an order by ID, eager load customer and order items
        $order = Order::with(['customer', 'orderItems.product'])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Find the order to update
            $order = Order::find($id);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Validate incoming request data. Only status and total_amount (if needed) are typically updated this way.
            // For modifying order items, a separate endpoint or more complex logic is usually required.
            $validatedData = $request->validate([
                'customer_id' => 'sometimes|exists:customers,id',
                'status' => 'sometimes|in:pending,processing,shipped,delivered,cancelled',
                // 'total_amount' => 'sometimes|numeric|min:0', // Usually recalculated, not directly updated
            ]);

            // Handle potential change in total_amount if order items are also being updated via a separate mechanism
            // For simplicity, this example only allows status and customer_id update.
            // If total_amount needs to be updated, it should be recalculated based on new order_items.

            $order->update($validatedData);
            return response()->json($order->load(['customer', 'orderItems.product'])); // Return updated order with relationships
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Find the order to delete
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Start a transaction to ensure stock is returned if order is cancelled/deleted
        DB::beginTransaction();
        try {
            // Before deleting, return the stock to products
            foreach ($order->orderItems as $item) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }

            // Delete the order record (this will also cascade delete order_items due to foreign key constraints)
            $order->delete();
            DB::commit();
            return response()->json(['message' => 'Order deleted successfully'], 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error deleting order: ' . $e->getMessage()], 500);
        }
    }
}
