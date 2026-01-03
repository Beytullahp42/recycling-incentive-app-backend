<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecyclableItemCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    public function recyclableItems()
    {
        return $this->hasMany(RecyclableItem::class, 'category_id');
    }

    protected static function booted()
    {
        static::deleting(function ($category) {
            if ($category->name === 'Uncategorized') {
                abort(403, 'Uncategorized category cannot be deleted.');
            }
        });

        static::updating(function ($category) {
            if ($category->getOriginal('name') === 'Uncategorized' && $category->isDirty('name')) {
                abort(403, 'The name of the Uncategorized category cannot be updated.');
            }
        });
    }
}
