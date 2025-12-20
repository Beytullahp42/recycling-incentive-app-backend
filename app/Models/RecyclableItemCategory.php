<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecyclableItemCategory extends Model
{
    /** @use HasFactory<\Database\Factories\RecyclableItemCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
    ];

    public function recyclableItems()
    {
        return $this->hasMany(RecyclableItem::class, 'category_id');
    }
}
