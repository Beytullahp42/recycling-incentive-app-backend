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
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(RecyclableItemCategory::class, 'category_id');
    }

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
}
