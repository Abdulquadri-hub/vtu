<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'currency',
        'daily_limit',
        'monthly_limit',
        'is_locked',
        'lock_reason',
        'last_activity',
        'settings'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'monthly_limit' => 'decimal:2',
        'is_locked' => 'boolean',
        'settings' => 'array',
        'last_activity' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    

    public function isLocked(): bool
    {
        return $this->is_locked;
    }

    public function lock(string $reason): void
    {
        $this->update([
            'is_locked' => true,
            'lock_reason' => $reason
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'lock_reason' => null
        ]);
    }
}
