<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecyclableItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'manual_value',
        'barcode',
        'category_id',
    ];

    // This ensures 'current_value' is always in the JSON response
    protected $appends = ['current_value'];

    public function category()
    {
        return $this->belongsTo(RecyclableItemCategory::class, 'category_id');
    }

    protected function casts(): array
    {
        return [
            'manual_value' => 'integer',
        ];
    }

    /*
     * THE LOGIC: Calculates price safely and efficiently
     */
    public function getCurrentValueAttribute()
    {
        // 1. Priority: Manual Override
        if ($this->manual_value !== null) {
            return $this->manual_value;
        }

        // 2. Priority: Category Value
        return $this->category?->value ?? 0;
    }
}
