<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

final class DeviceIdTest extends TestCase
{
    public function test_it_generates_valid_distinct_ulid_identifiers(): void
    {
        $deviceId = DeviceId::generate();
        $userId = Ulid::random();

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $deviceId->value());
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $userId->getValue());
        $this->assertNotSame($deviceId->value(), $userId->getValue());
    }

    public function test_it_normalizes_and_compares_identifier_values(): void
    {
        $deviceId = DeviceId::fromString('01j9qz3gs8kecg8zzcs53g6ydw');

        $this->assertSame('01J9QZ3GS8KECG8ZZCS53G6YDW', $deviceId->value());
        $this->assertTrue($deviceId->equals(DeviceId::fromString('01J9QZ3GS8KECG8ZZCS53G6YDW')));
    }

    public function test_it_rejects_an_invalid_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DeviceId::fromString('device-123');
    }
}
