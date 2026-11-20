<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Service;

use DateTimeImmutable;
use Src\IAM\Domain\Entity\Device;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Events\NewDeviceDetected;
use Src\IAM\Domain\Exceptions\DeviceBlockedException;
use Src\IAM\Domain\Exceptions\UnrecognizedDeviceException;
use Src\IAM\Domain\Ports\DeviceRepositoryInterface;
use Src\IAM\Domain\ValueObjects\DeviceId;

/**
 * Coordinates device-authentication rules spanning the User and Device domains.
 *
 * This service remains framework-agnostic: persistence is accessed exclusively
 * through the domain repository port, and its domain events are made available
 * to the caller through pullEvents().
 */
final class UserDeviceAuthenticationService
{
    /** @var list<NewDeviceDetected> */
    private array $recordedEvents = [];

    public function __construct(private readonly DeviceRepositoryInterface $deviceRepository)
    {
    }

    /**
     * @throws DeviceBlockedException
     * @throws UnrecognizedDeviceException
     */
    public function authenticateDevice(User $user, DeviceId $deviceId, string $ipAddress): Device
    {
        $device = $this->deviceRepository->findByUserIdAndDeviceId($user->id, $deviceId);

        if ($device === null) {
            $device = Device::create($deviceId, $user->id);
            $this->recordEvent(new NewDeviceDetected(
                userId: $user->id,
                deviceId: $deviceId,
                ipAddress: $ipAddress,
                occurredAt: new DateTimeImmutable(),
            ));
            $this->deviceRepository->save($device);

            throw UnrecognizedDeviceException::create();
        }

        if ($device->isBlocked()) {
            throw DeviceBlockedException::create();
        }

        if (! $device->isTrusted()) {
            throw UnrecognizedDeviceException::create();
        }

        $device->markAsUsed(new DateTimeImmutable(), $ipAddress);
        $this->deviceRepository->save($device);

        return $device;
    }

    /**
     * @return list<NewDeviceDetected>
     */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function recordEvent(NewDeviceDetected $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
