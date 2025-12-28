<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\SessionLifecycle;
use App\Enums\TransactionStatus;

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
        'ended_at',
        'lifecycle_status',
        'audit_status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at'   => 'datetime',
        'lifecycle_status' => SessionLifecycle::class,
        'audit_status'     => TransactionStatus::class,
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

    public function acceptedTransactions()
    {
        return $this->hasMany(Transaction::class)->where('status', TransactionStatus::ACCEPTED);
    }

    public function flaggedTransactions()
    {
        return $this->hasMany(Transaction::class)->where('status', TransactionStatus::FLAGGED);
    }

    public function rejectedTransactions()
    {
        return $this->hasMany(Transaction::class)->where('status', TransactionStatus::REJECTED);
    }

    public function getAcceptedPointsAttribute($value): int
    {
        return $value ?? 0;
    }
    public function getFlaggedPointsAttribute($value): int
    {
        return $value ?? 0;
    }
    public function getRejectedPointsAttribute($value): int
    {
        return $value ?? 0;
    }
}
