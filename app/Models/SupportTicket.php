<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model {
    
    protected $fillable = [
        'user_id', 'ticket_number', 'subject', 'description', 'status', 'priority', 'transaction_id', 'resolved_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
