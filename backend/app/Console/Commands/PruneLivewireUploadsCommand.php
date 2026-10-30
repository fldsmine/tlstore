<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

#[Signature('livewire:prune-uploads')]
#[Description('Delete Livewire temporary upload files (livewire-tmp) older than 24 hours.')]
class PruneLivewireUploadsCommand extends Command
{
    public function handle(): int
    {

        if (! FileUploadConfiguration::shouldCleanupOldUploads()) {
            $this->info('Temporary-upload cleanup is disabled (livewire.temporary_file_upload.cleanup).');

            return self::SUCCESS;
        }

        if (FileUploadConfiguration::isUsingS3()) {
            $this->info('Temporary uploads use S3 — cleanup is handled by a bucket lifecycle rule (livewire:configure-s3-upload-cleanup).');

            return self::SUCCESS;
        }

        $storage = FileUploadConfiguration::storage();
        $cutoff = now()->subDay()->timestamp;
        $deleted = 0;

        foreach ($storage->allFiles(FileUploadConfiguration::path()) as $file) {

            if (! $storage->exists($file)) {
                continue;
            }

            if ($storage->lastModified($file) < $cutoff) {
                $storage->delete($file);
                $deleted++;
            }
        }

        $this->info("livewire:prune-uploads — removed {$deleted} temporary upload file(s) older than 24h.");

        return self::SUCCESS;
    }
}
