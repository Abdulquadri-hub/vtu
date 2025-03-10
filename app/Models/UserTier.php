<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTier extends Model
{
    protected $fillable = [
        'name', 'monthly_transaction_limit', 'single_transaction_limit', 'benefits'
    ];

    protected $casts = [
        'benefits' => 'array',
    ];
}
