<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundlingMaterial extends Model
{
    protected $fillable = [
        'bundling_id',
        'product_id',
        'quantity'
    ];

    public function bundling()
    {
        return $this->belongsTo(Bundling::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
