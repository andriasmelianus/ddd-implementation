<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\IAM\Domain\Entity\Device;
use Src\IAM\Domain\Ports\DeviceRepositoryInterface;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * In-memory implementation of the device write port for domain unit tests.
 */
final class InMemoryDeviceRepository implements DeviceRepositoryInterface
{
    /** @var array<string, Device> */
    private array $devices = [];

    public function findById(DeviceId $deviceId): ?Device
    {
        foreach ($this->devices as $device) {
            if ($device->id()->equals($deviceId)) {
                return $device;
            }
        }

        return null;
    }

    public function findByUserIdAndDeviceId(Ulid $userId, DeviceId $deviceId): ?Device
    {
        $device = $this->findById($deviceId);

        if ($device === null || ! $device->userId()->equals($userId)) {
            return null;
        }

        return $device;
    }

    /**
     * @return list<Device>
     */
    public function findByUserId(Ulid $userId): array
    {
        return array_values(array_filter(
            $this->devices,
            static fn (Device $device): bool => $device->userId()->equals($userId),
        ));
    }

    public function save(Device $device): void
    {
        $this->devices[$device->id()->value()] = $device;
    }

    public function count(): int
    {
        return count($this->devices);
    }
}
