<?php

// app/Http/Controllers/Api/CustomerController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retrieve all customers
        $customers = Customer::all();
        return response()->json($customers);
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
                'email' => 'required|string|email|max:255|unique:customers,email',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string',
            ]);

            // Create a new customer record
            $customer = Customer::create($validatedData);
            return response()->json($customer, 201); // 201 Created
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating customer: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find a customer by ID
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404); // 404 Not Found
        }

        return response()->json($customer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Find the customer to update
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            // Validate incoming request data, ensuring email is unique except for the current customer
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:customers,email,' . $customer->id,
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string',
            ]);

            // Update the customer record
            $customer->update($validatedData);
            return response()->json($customer); // 200 OK
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating customer: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Find the customer to delete
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        // Delete the customer record
        $customer->delete();
        return response()->json(['message' => 'Customer deleted successfully'], 204); // 204 No Content
    }
}
