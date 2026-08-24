<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'author_id',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Autogenera el slug desde el título si no viene definido, garantizando
        // unicidad (igual criterio que el SKU autogenerado de Product).
        static::creating(function (Post $post) {
            if (blank($post->slug)) {
                $post->slug = static::generarSlugUnico($post->title);
            }
        });
    }

    protected static function generarSlugUnico(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $intento = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$intento}";
            $intento++;
        }

        return $slug;
    }

    // Relaciones
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Scopes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Convierte una ruta relativa del disco público en URL absoluta.
     * Idempotente: si ya es una URL (http) la devuelve sin cambios.
     */
    public static function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
