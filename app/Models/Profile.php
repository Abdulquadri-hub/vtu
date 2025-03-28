<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
        'user_id', 'bvn', 'nin', 'bvn_dob', 'profile_picture',
        'wallet_reference', 'wallet_balance', 'wallet_status',
        'account_name', 'account_number', 'bank_name', 'bank_code'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Add accessors for formatted balance
    public function getFormattedBalanceAttribute()
    {
        return number_format($this->wallet_balance, 2);
    }
}
