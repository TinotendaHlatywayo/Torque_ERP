<?php

namespace App\Support;

final class PaynowHash
{
    public static function make(array $values, string $integrationKey): string
    {
        $message = '';
        foreach ($values as $key => $value) {
            if (strtoupper((string) $key) !== 'HASH') {
                $message .= (string) $value;
            }
        }

        return strtoupper(hash('sha512', $message.$integrationKey));
    }

    public static function valid(array $values, string $integrationKey): bool
    {
        $provided = null;
        foreach ($values as $key => $value) {
            if (strtoupper((string) $key) === 'HASH') {
                $provided = (string) $value;
                break;
            }
        }

        return is_string($provided)
            && preg_match('/^[A-Fa-f0-9]{128}$/', $provided) === 1
            && hash_equals(self::make($values, $integrationKey), strtoupper($provided));
    }
}
