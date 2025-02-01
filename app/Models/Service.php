<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'provider_id', 'name', 'description',
        'amount', 'provider_amount', 'is_active'
    ];

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
