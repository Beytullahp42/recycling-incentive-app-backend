<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecyclableItem extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'value',
        'barcode',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($item) {
            if (is_null($item->value)) {
                $item->value = 5; // Default value
            }
        });
    }
}
