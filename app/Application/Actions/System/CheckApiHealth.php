<?php

namespace App\Application\Actions\System;

/**
 * Application-level health check. Transport controllers may call this action
 * without coupling application behavior to HTTP request or response objects.
 */
final class CheckApiHealth
{
    /** @return array{status: string} */
    public function handle(): array
    {
        return ['status' => 'ok'];
    }
}
