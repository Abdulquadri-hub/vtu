<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitRecord extends Model
{
    protected $fillable = [
        'transaction_id', 'amount', 'type'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
