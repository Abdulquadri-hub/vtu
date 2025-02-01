<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'type', 'amount',
        'fee', 'profit', 'status', 'meta_data',
        'provider_reference'
    ];

    protected $casts = [
        'meta_data' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profitRecord()
    {
        return $this->hasOne(ProfitRecord::class);
    }
}
