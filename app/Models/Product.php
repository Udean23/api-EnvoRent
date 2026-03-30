<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'category_id',
        'image',
        'description',
        'stock',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function transactionMaterials()
    {
        return $this->hasMany(TransactionMaterial::class);
    }
}
