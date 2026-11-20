<?php

declare(strict_types=1);

namespace Src\IAM\Application\Command;

use Src\Shared\Framework\Application\Commands\CommandInterface;

/**
 * Immutable input for an authentication attempt bound to a client device.
 *
 * Format validation belongs to the HTTP request. This command deliberately
 * carries scalar input only so that the application handler creates the
 * domain value objects at its boundary.
 */
final readonly class AuthenticateWithDeviceCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceId,
        public string $ipAddress,
    ) {
    }
}
