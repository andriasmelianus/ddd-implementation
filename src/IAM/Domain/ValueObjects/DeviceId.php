<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable, framework-independent identity of a client device.
 */
final readonly class DeviceId implements JsonSerializable, Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Device ID must be a valid 26-character ULID.');
        }

        return new self($normalized);
    }

    public static function generate(): self
    {
        $timestamp = (int) floor(microtime(true) * 1000);
        $timestampBytes = substr(pack('J', $timestamp), -6);

        return self::fromString(self::encodeCrockford($timestampBytes.random_bytes(10)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function encodeCrockford(string $bytes): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $bits = '';

        foreach (unpack('C*', $bytes) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $bits = '00'.$bits;
        $encoded = '';

        for ($offset = 0; $offset < strlen($bits); $offset += 5) {
            $encoded .= $alphabet[bindec(substr($bits, $offset, 5))];
        }

        return $encoded;
    }
}
