<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageCompressionService
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    public function compressAndStore(
        UploadedFile $file,
        string $path,
        ?string $disk = null,
        int $quality = 80,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
    ): string {
        $disk ??= config('filesystems.default');

        $image = $this->imageManager->read($file->getPathname());

        if ($maxWidth !== null || $maxHeight !== null) {
            $image->scaleDown(width: $maxWidth, height: $maxHeight);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $encoded = match ($extension) {
            'png' => $image->toPng(),
            'webp' => $image->toWebp($quality),
            'gif' => $image->toGif(),
            default => $image->toJpeg($quality),
        };

        $fullPath = trim($path, '/').'/'.Str::uuid7()->toString().'.'.$extension;

        // No per-object ACL — Linode Object Storage has ACLs disabled; public read is granted by a
        // bucket policy on the image prefixes. Local disks serve these prefixes via the storage symlink.
        Storage::disk($disk)->put($fullPath, (string) $encoded);

        return $fullPath;
    }
}
