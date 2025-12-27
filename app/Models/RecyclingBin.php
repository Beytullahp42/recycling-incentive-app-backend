<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RecyclingBin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'qr_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function ($bin) {
            if (empty($bin->qr_key)) {
                do {
                    $key = Str::random(16);
                } while (static::where('qr_key', $key)->exists());

                $bin->qr_key = $key;
            }
        });
    }
}
