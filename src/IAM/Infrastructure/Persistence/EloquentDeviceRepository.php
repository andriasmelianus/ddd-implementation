<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Persistence;

use DateTimeImmutable;
use Src\IAM\Domain\Entity\Device;
use Src\IAM\Domain\Ports\DeviceRepositoryInterface;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\IAM\Domain\ValueObjects\DeviceStatus;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

final readonly class EloquentDeviceRepository implements DeviceRepositoryInterface
{
    public function findById(DeviceId $deviceId): ?Device
    {
        $model = DeviceModel::query()->find($deviceId->value());

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByUserIdAndDeviceId(Ulid $userId, DeviceId $deviceId): ?Device
    {
        $model = DeviceModel::query()
            ->where('user_id', $userId->getValue())
            ->whereKey($deviceId->value())
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * @return list<Device>
     */
    public function findByUserId(Ulid $userId): array
    {
        return DeviceModel::query()
            ->where('user_id', $userId->getValue())
            ->get()
            ->map(fn (DeviceModel $model): Device => $this->toDomain($model))
            ->all();
    }

    public function save(Device $device): void
    {
        $model = DeviceModel::query()->find($device->id()->value()) ?? new DeviceModel;

        $model->forceFill([
            'id' => $device->id()->value(),
            'user_id' => $device->userId()->getValue(),
            'status' => $device->status()->value,
            'last_used_at' => $device->lastUsedAt(),
            'ip_address' => $device->ipAddress(),
        ]);

        $model->save();
    }

    private function toDomain(DeviceModel $model): Device
    {
        return Device::reconstitute(
            id: DeviceId::fromString((string) $model->getKey()),
            userId: new Ulid((string) $model->user_id),
            status: DeviceStatus::from((string) $model->status),
            lastUsedAt: $model->last_used_at === null
                ? null
                : DateTimeImmutable::createFromInterface($model->last_used_at),
            ipAddress: $model->ip_address === null ? null : (string) $model->ip_address,
        );
    }
}
