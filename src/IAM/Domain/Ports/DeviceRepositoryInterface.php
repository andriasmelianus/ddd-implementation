<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Ports;

use Src\IAM\Domain\Entity\Device;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * Write-side persistence port for known user devices.
 */
interface DeviceRepositoryInterface
{
    public function findById(DeviceId $deviceId): ?Device;

    /**
     * Returns the device only when it belongs to the supplied user.
     */
    public function findByUserIdAndDeviceId(Ulid $userId, DeviceId $deviceId): ?Device;

    /**
     * @return list<Device>
     */
    public function findByUserId(Ulid $userId): array;

    public function save(Device $device): void;
}
