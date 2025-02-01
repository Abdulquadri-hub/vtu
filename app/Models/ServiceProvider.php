<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    protected $fillable = [
        'name', 'type', 'is_active', 'configuration'
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean'
    ];

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
