<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product3dModel extends Model
{
    use HasUuid;

    protected $table = 'product_3d_models';

    protected $fillable = [
        'product_id',
        'file_glb_url',
        'file_draco_url',
        'preview_url',
        'file_size_kb',
        'version',
    ];

    protected $casts = [
        'file_size_kb' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
