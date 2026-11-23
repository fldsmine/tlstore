<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SetBucketPolicy extends Command
{
    protected $signature = 'storage:bucket-policy';

    protected $description = 'Grant public read on the S3 bucket media prefixes.';

    private const PREFIXES = ['avatars', 'admin-avatars', 'ad-images', 'cloud-avatars', 'cloud-voices'];

    public function handle(): int
    {
        if (config('filesystems.default') !== 's3') {
            $this->info('Default disk is not s3 — local disks serve media via the storage symlink; nothing to do.');

            return self::SUCCESS;
        }

        $bucket = (string) config('filesystems.disks.s3.bucket');

        if (blank($bucket)) {
            $this->error('No S3 bucket configured.');

            return self::FAILURE;
        }

        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Sid' => 'PublicReadImagePrefixes',
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => array_map(fn (string $prefix): string => "arn:aws:s3:::{$bucket}/{$prefix}/*", self::PREFIXES),
            ]],
        ], JSON_UNESCAPED_SLASHES);

        Storage::disk('s3')->getClient()->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $policy]);

        $this->info('Public-read policy applied on '.$bucket.' for: '.implode(', ', self::PREFIXES));

        return self::SUCCESS;
    }
}
