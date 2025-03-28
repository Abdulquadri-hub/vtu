<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservedAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'reference',
        'account_name',
        'account_number',
        'bank_name',
        'bank_code',
        'status',
        'provider',
        'provider_reference',
        'meta_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'meta_data' => 'array',
    ];

    /**
     * Get the user that owns the reserved account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet associated with the reserved account.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
