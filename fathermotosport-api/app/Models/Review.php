<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'title',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
