<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gestiona la subida de archivos a Cloudflare R2 (compatible S3).
 * Configurar el disco 'r2' en config/filesystems.php con las credenciales en .env.
 */
class StorageService
{
    private string $disk = 'r2';

    /**
     * Sube una imagen y retorna su URL pública.
     */
    public function uploadImage(UploadedFile $file, string $folder = 'images'): string
    {
        $path = $this->store($file, $folder);

        return $this->url($path);
    }

    /**
     * Sube un modelo GLB a models/{productId}/ y retorna su URL pública.
     */
    public function uploadModel(UploadedFile $file, string $productId): string
    {
        $path = $this->store($file, "models/{$productId}");

        return $this->url($path);
    }

    /**
     * Elimina un archivo de R2 a partir de su URL pública.
     */
    public function deleteFile(string $url): bool
    {
        $path = $this->pathFromUrl($url);

        if (! $path) {
            return false;
        }

        return Storage::disk($this->disk)->delete($path);
    }

    private function store(UploadedFile $file, string $folder): string
    {
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();

        return Storage::disk($this->disk)->putFileAs($folder, $file, $name, 'public');
    }

    private function url(string $path): string
    {
        // Usa la URL pública del bucket (R2_URL) si está configurada.
        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');

        return $base ? "{$base}/{$path}" : Storage::disk($this->disk)->url($path);
    }

    private function pathFromUrl(string $url): ?string
    {
        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');

        if ($base && str_starts_with($url, $base)) {
            return ltrim(substr($url, strlen($base)), '/');
        }

        return ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/') ?: null;
    }
}
