<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-genera un UUID v4 como primary key al crear el modelo.
 * Configura la key como string no incremental.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function initializeHasUuid(): void
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }
}
