<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePricingHistory extends Model
{
    protected $fillable = [
        'service_id', 'old_amount', 'new_amount', 'old_provider_amount', 'new_provider_amount', 'change_reason'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
