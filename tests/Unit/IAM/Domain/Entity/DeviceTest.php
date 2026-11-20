<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\Entity\Device;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\IAM\Domain\ValueObjects\DeviceStatus;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

final class DeviceTest extends TestCase
{
    public function test_a_new_device_is_untrusted_and_has_an_owner(): void
    {
        $deviceId = DeviceId::generate();
        $userId = Ulid::random();

        $device = Device::create($deviceId, $userId);

        $this->assertSame($deviceId, $device->id());
        $this->assertSame($userId, $device->userId());
        $this->assertSame(DeviceStatus::UNTRUSTED, $device->status());
        $this->assertNull($device->lastUsedAt());
        $this->assertNull($device->ipAddress());
        $this->assertFalse($device->isTrusted());
        $this->assertFalse($device->isBlocked());
    }

    public function test_it_records_its_latest_usage_metadata(): void
    {
        $device = Device::create(DeviceId::generate(), Ulid::random());
        $usedAt = new DateTimeImmutable('2026-11-20T06:00:00+00:00');

        $device->markAsUsed($usedAt, '203.0.113.10');

        $this->assertSame($usedAt, $device->lastUsedAt());
        $this->assertSame('203.0.113.10', $device->ipAddress());
    }

    public function test_it_rejects_an_invalid_ip_address(): void
    {
        $device = Device::create(DeviceId::generate(), Ulid::random());

        $this->expectException(InvalidArgumentException::class);
        $device->markAsUsed(new DateTimeImmutable(), 'not-an-ip-address');
    }

    public function test_a_blocked_device_cannot_be_trusted_again(): void
    {
        $device = Device::create(DeviceId::generate(), Ulid::random());

        $device->trust();
        $this->assertTrue($device->isTrusted());

        $device->block();
        $device->trust();

        $this->assertTrue($device->isBlocked());
        $this->assertSame(DeviceStatus::BLOCKED, $device->status());
    }
}
