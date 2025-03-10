<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{
    protected $fillable = [
        'service_type', 'commission_percentage', 'minimum_amount', 'maximum_amount', 'is_active'
    ];
}
