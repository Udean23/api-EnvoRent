<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bundling extends Model
{
    protected $fillable = [
        'name',
        'price',
        'category_id',
        'image',
        'description',
    ];

    public function materials()
    {
        return $this->hasMany(BundlingMaterial::class);
    }
}
