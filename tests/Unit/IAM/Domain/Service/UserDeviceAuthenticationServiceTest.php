<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\Entity\Device;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Events\NewDeviceDetected;
use Src\IAM\Domain\Exceptions\DeviceBlockedException;
use Src\IAM\Domain\Exceptions\UnrecognizedDeviceException;
use Src\IAM\Domain\Service\UserDeviceAuthenticationService;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\DeviceStatus;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;
use Tests\Unit\IAM\Support\InMemoryDeviceRepository;

final class UserDeviceAuthenticationServiceTest extends TestCase
{
    private InMemoryDeviceRepository $devices;

    private UserDeviceAuthenticationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->devices = new InMemoryDeviceRepository();
        $this->service = new UserDeviceAuthenticationService($this->devices);
    }

    public function test_a_new_device_is_saved_as_untrusted_records_an_event_and_is_rejected(): void
    {
        $user = $this->user();
        $deviceId = DeviceId::generate();

        try {
            $this->service->authenticateDevice($user, $deviceId, '203.0.113.10');
            self::fail('Expected an unrecognized-device exception.');
        } catch (UnrecognizedDeviceException) {
        }

        $device = $this->devices->findByUserIdAndDeviceId($user->id, $deviceId);
        self::assertNotNull($device);
        self::assertSame(DeviceStatus::UNTRUSTED, $device->status());
        self::assertNull($device->lastUsedAt());
        self::assertNull($device->ipAddress());
        self::assertSame(1, $this->devices->count());

        $events = $this->service->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewDeviceDetected::class, $events[0]);
        self::assertSame($user->id, $events[0]->userId);
        self::assertSame($deviceId, $events[0]->deviceId);
        self::assertSame('203.0.113.10', $events[0]->ipAddress);
        self::assertInstanceOf(DateTimeImmutable::class, $events[0]->occurredAt);
        self::assertSame([], $this->service->pullEvents());
    }

    public function test_it_rejects_a_blocked_device_without_updating_usage_metadata(): void
    {
        $user = $this->user();
        $device = Device::create(DeviceId::generate(), $user->id);
        $previousUsage = new DateTimeImmutable('2026-11-20T00:00:00+00:00');
        $device->markAsUsed($previousUsage, '198.51.100.2');
        $device->block();
        $this->devices->save($device);

        $this->expectException(DeviceBlockedException::class);

        try {
            $this->service->authenticateDevice($user, $device->id(), '203.0.113.10');
        } finally {
            self::assertSame($previousUsage, $device->lastUsedAt());
            self::assertSame('198.51.100.2', $device->ipAddress());
            self::assertSame([], $this->service->pullEvents());
        }
    }

    public function test_a_trusted_device_is_authenticated_and_its_usage_metadata_is_updated(): void
    {
        $user = $this->user();
        $device = Device::create(DeviceId::generate(), $user->id);
        $device->trust();
        $this->devices->save($device);

        $authenticatedDevice = $this->service->authenticateDevice($user, $device->id(), '2001:db8::1');

        self::assertSame($device, $authenticatedDevice);
        self::assertTrue($authenticatedDevice->isTrusted());
        self::assertSame('2001:db8::1', $authenticatedDevice->ipAddress());
        self::assertInstanceOf(DateTimeImmutable::class, $authenticatedDevice->lastUsedAt());
        self::assertSame([], $this->service->pullEvents());
    }

    private function user(): User
    {
        return User::reconstitute(
            id: Ulid::random(),
            email: EmailAddress::fromString('user@example.com'),
            hashedPassword: 'hashed-password',
            status: AccountStatus::ACTIVE,
            failedLoginAttempts: 0,
        );
    }
}
