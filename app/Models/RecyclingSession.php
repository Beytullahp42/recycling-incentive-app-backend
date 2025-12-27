<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecyclingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recycling_bin_id',
        'session_token',
        'proof_photo_path',
        'started_at',
        'expires_at',
        'status'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function bin()
    {
        return $this->belongsTo(RecyclingBin::class, 'recycling_bin_id');
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
