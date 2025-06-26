<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderItemController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retrieve all order items, eager load related order and product
        $orderItems = OrderItem::with(['order', 'product'])->get();
        return response()->json($orderItems);
    }

    /**
     * Store a newly created resource in storage.
     * Note: Typically order items are added via the OrderController's store method.
     * This method allows adding an item to an *existing* order.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'order_id' => 'required|exists:orders,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            $order = Order::find($validatedData['order_id']);
            $product = Product::find($validatedData['product_id']);

            if (!$order) {
                DB::rollBack();
                return response()->json(['message' => 'Order not found.'], 404);
            }
            if (!$product) {
                DB::rollBack();
                return response()->json(['message' => 'Product not found.'], 404);
            }
            if ($product->stock < $validatedData['quantity']) {
                DB::rollBack();
                return response()->json(['message' => 'Not enough stock for product: ' . $product->name], 400);
            }

            // Create the order item
            $orderItem = OrderItem::create([
                'order_id' => $validatedData['order_id'],
                'product_id' => $validatedData['product_id'],
                'quantity' => $validatedData['quantity'],
                'price_at_order' => $product->price, // Record current price
            ]);

            // Update order's total amount and decrease product stock
            $order->total_amount += ($product->price * $validatedData['quantity']);
            $order->save();
            $product->decrement('stock', $validatedData['quantity']);

            DB::commit();

            return response()->json($orderItem->load(['order', 'product']), 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating order item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find an order item by ID
        $orderItem = OrderItem::with(['order', 'product'])->find($id);

        if (!$orderItem) {
            return response()->json(['message' => 'Order Item not found'], 404);
        }

        return response()->json($orderItem);
    }

    /**
     * Update the specified resource in storage.
     * Note: Updating quantity here will adjust stock and parent order's total_amount.
     */
    public function update(Request $request, string $id)
    {
        try {
            $orderItem = OrderItem::find($id);

            if (!$orderItem) {
                return response()->json(['message' => 'Order Item not found'], 404);
            }

            $validatedData = $request->validate([
                'quantity' => 'sometimes|integer|min:1',
            ]);

            DB::beginTransaction();

            $order = $orderItem->order;
            $product = $orderItem->product;

            // Calculate the change in quantity
            $oldQuantity = $orderItem->quantity;
            $newQuantity = $validatedData['quantity'] ?? $oldQuantity;
            $quantityDiff = $newQuantity - $oldQuantity;

            if ($quantityDiff > 0) {
                // If increasing quantity, check stock
                if ($product->stock < $quantityDiff) {
                    DB::rollBack();
                    return response()->json(['message' => 'Not enough stock for product: ' . $product->name], 400);
                }
                $product->decrement('stock', $quantityDiff);
            } elseif ($quantityDiff < 0) {
                // If decreasing quantity, return stock
                $product->increment('stock', abs($quantityDiff));
            }

            // Update the order item
            $orderItem->update(['quantity' => $newQuantity]);

            // Recalculate order total (safer than incremental adjustments for updates)
            $order->total_amount = $order->orderItems()->sum(DB::raw('quantity * price_at_order'));
            $order->save();

            DB::commit();

            return response()->json($orderItem->load(['order', 'product']));
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating order item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * Note: Deleting an order item will adjust the parent order's total_amount and return product stock.
     */
    public function destroy(string $id)
    {
        $orderItem = OrderItem::find($id);

        if (!$orderItem) {
            return response()->json(['message' => 'Order Item not found'], 404);
        }

        DB::beginTransaction();
        try {
            $order = $orderItem->order;
            $product = $orderItem->product;

            // Return stock to the product
            if ($product) {
                $product->increment('stock', $orderItem->quantity);
            }

            // Decrement the order's total amount
            if ($order) {
                $order->total_amount -= ($orderItem->price_at_order * $orderItem->quantity);
                $order->save();
            }

            // Delete the order item
            $orderItem->delete();
            DB::commit();
            return response()->json(['message' => 'Order Item deleted successfully'], 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error deleting order item: ' . $e->getMessage()], 500);
        }
    }
}
