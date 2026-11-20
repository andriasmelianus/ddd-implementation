<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\IAM\Domain\ValueObjects\DeviceStatus;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * A device known by the IAM domain and owned by exactly one user.
 */
final class Device
{
    private function __construct(
        private readonly DeviceId $id,
        private readonly Ulid $userId,
        private DeviceStatus $status,
        private ?DateTimeImmutable $lastUsedAt,
        private ?string $ipAddress,
    ) {
    }

    public static function create(DeviceId $id, Ulid $userId): self
    {
        return new self(
            id: $id,
            userId: $userId,
            status: DeviceStatus::UNTRUSTED,
            lastUsedAt: null,
            ipAddress: null,
        );
    }

    public static function reconstitute(
        DeviceId $id,
        Ulid $userId,
        DeviceStatus $status,
        ?DateTimeImmutable $lastUsedAt,
        ?string $ipAddress,
    ): self {
        if ($ipAddress !== null) {
            self::ensureValidIpAddress($ipAddress);
        }

        return new self($id, $userId, $status, $lastUsedAt, $ipAddress);
    }

    public function id(): DeviceId
    {
        return $this->id;
    }

    public function userId(): Ulid
    {
        return $this->userId;
    }

    public function status(): DeviceStatus
    {
        return $this->status;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function markAsUsed(DateTimeImmutable $usedAt, string $ipAddress): void
    {
        self::ensureValidIpAddress($ipAddress);

        $this->lastUsedAt = $usedAt;
        $this->ipAddress = $ipAddress;
    }

    public function trust(): void
    {
        if (! $this->isBlocked()) {
            $this->status = DeviceStatus::TRUSTED;
        }
    }

    public function block(): void
    {
        $this->status = DeviceStatus::BLOCKED;
    }

    public function isBlocked(): bool
    {
        return $this->status->isBlocked();
    }

    public function isTrusted(): bool
    {
        return $this->status->isTrusted();
    }

    private static function ensureValidIpAddress(string $ipAddress): void
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Device IP address must be a valid IPv4 or IPv6 address.');
        }
    }
}
