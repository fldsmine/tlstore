<?php

namespace App\Application\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class TotpAuthenticator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function createSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $secret, string $account, string $issuer = 'TL Store'): string
    {
        $label = rawurlencode($issuer.':'.$account);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    public function verify(string $secret, string $code, ?int $unixTime = null): bool
    {
        return $this->matchingCounter($secret, $code, $unixTime) !== null;
    }

    public function matchingCounter(string $secret, string $code, ?int $unixTime = null): ?int
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) return null;
        $key = $this->base32Decode($secret);
        $counter = intdiv($unixTime ?? time(), 30);

        // Permit one 30-second step of clock drift on either side.
        for ($offset = -1; $offset <= 1; $offset++) {
            $value = $counter + $offset;
            $message = pack('N2', ($value >> 32) & 0xffffffff, $value & 0xffffffff);
            $hash = hash_hmac('sha1', $message, $key, true);
            $position = ord($hash[19]) & 0x0f;
            $binary = unpack('N', substr($hash, $position, 4))[1] & 0x7fffffff;
            $expected = str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
            if (hash_equals($expected, $code)) return $value;
        }

        return null;
    }

    /** @return list<string> */
    public function createRecoveryCodes(): array
    {
        return array_map(static fn (): string => Str::upper(Str::random(4).'-'.Str::random(4)), range(1, 8));
    }

    /** @param list<string> $codes @return list<string> */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(static fn (string $code): string => Hash::make(strtoupper($code)), $codes);
    }

    public function verifyAndConsume(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $secret = $locked->two_factor_secret;
            $counter = is_string($secret) ? $this->matchingCounter($secret, trim($code)) : null;
            if ($counter !== null && ($locked->two_factor_last_counter === null || $counter > $locked->two_factor_last_counter)) {
                $locked->two_factor_last_counter = $counter;
                $locked->save();
                $user->two_factor_last_counter = $counter;

                return true;
            }

            $codes = $locked->two_factor_recovery_codes ?? [];
            foreach ($codes as $index => $hash) {
                if (Hash::check(strtoupper(trim($code)), $hash)) {
                    unset($codes[$index]);
                    $locked->two_factor_recovery_codes = array_values($codes);
                    $locked->save();
                    $user->two_factor_recovery_codes = array_values($codes);

                    return true;
                }
            }

            return false;
        });
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        $bits = '';
        foreach (str_split($encoded) as $character) {
            $index = strpos(self::ALPHABET, $character);
            if ($index === false) return '';
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }
}
