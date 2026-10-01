<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'brand_id',
    'name',
    'slug',
    'description',
    'price',
    'compare_at_price',
    'emoji',
    'image_url',
    'badge',
    'featured',
    'is_deal',
    'rating',
    'reviews_count',
    'rating_chips',
    'options',
    'delivery_standard',
    'delivery_pickup',
    'stock',
])]
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'rating' => 'decimal:2',
            'featured' => 'boolean',
            'is_deal' => 'boolean',
            'rating_chips' => 'array',
            'options' => 'array',
            'delivery_standard' => 'boolean',
            'delivery_pickup' => 'boolean',
            'stock' => 'integer',
            'reviews_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
