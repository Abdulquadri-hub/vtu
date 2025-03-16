<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    //
    protected $fillable = [
        'user_id',
        'account_reference',
        'account_name',
        'currency_code',
        'nin',
        'bnv',
        'getAllAvailableBanks'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
