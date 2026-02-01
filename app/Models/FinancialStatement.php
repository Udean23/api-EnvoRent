<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialStatement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'start_period',
        'end_period',
        'transaction_count',
        'income'
    ];
}
