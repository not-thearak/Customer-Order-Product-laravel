<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'image_url',
    ];

    /**
     * The order items that belong to the product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
