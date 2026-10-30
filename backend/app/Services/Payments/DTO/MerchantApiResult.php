<?php

namespace App\Services\Payments\DTO;

/**
 * Outcome of a merchant/backoffice API call (balance, balance history, bank resolve).
 * `ok` is false when the call could not be completed — `data` then carries no authoritative
 * figures and the caller must show `message` instead of inventing a result.
 */
final class MerchantApiResult
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(
        public bool $ok,
        public array $data = [],
        public ?string $message = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function success(array $data = [], ?string $message = null): self
    {
        return new self(true, $data, $message);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function failure(string $message, array $data = []): self
    {
        return new self(false, $data, $message);
    }
}
