<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionMaterial extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'bundling_id'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bundling()
    {
        return $this->belongsTo(Bundling::class);
    }
}
