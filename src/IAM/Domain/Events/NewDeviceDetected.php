<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Events;

use DateTimeImmutable;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * Raised when a user authenticates from a previously unknown device.
 */
final readonly class NewDeviceDetected
{
    public function __construct(
        public Ulid $userId,
        public DeviceId $deviceId,
        public string $ipAddress,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function getName(): string
    {
        return 'iam.device.new_detected';
    }
}
