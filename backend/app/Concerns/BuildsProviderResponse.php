<?php

namespace App\Concerns;

use Throwable;

trait BuildsProviderResponse
{
    protected function success(string $message, array $data = []): array
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function error(string $message, array $data = []): array
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($data !== []) {
            $response['data'] = $data;
        }

        return $response;
    }

    protected function failed(Throwable $e, string $prefix = ''): array
    {
        return $this->error(trim($prefix.' '.$e->getMessage()));
    }
}
