<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recycling_session_id',
        'recyclable_item_id', // <--- Added
        'barcode',
        'points_awarded',
        'status',
    ];

    /**
     * Get the session this transaction belongs to.
     */
    public function session()
    {
        return $this->belongsTo(RecyclingSession::class, 'recycling_session_id');
    }

    /**
     * Get the user who scanned this item.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
