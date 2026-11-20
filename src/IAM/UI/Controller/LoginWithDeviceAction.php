<?php

declare(strict_types=1);

namespace Src\IAM\UI\Controller;

use Illuminate\Http\JsonResponse;
use Src\IAM\Application\Command\AuthenticateWithDeviceCommandHandler;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\DeviceBlockedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\Exceptions\UnrecognizedDeviceException;
use Src\IAM\UI\Request\LoginWithDeviceRequest;
use Src\IAM\UI\Resource\AuthTokenRes;

final readonly class LoginWithDeviceAction
{
    public function __construct(private AuthenticateWithDeviceCommandHandler $handler) {}

    public function __invoke(LoginWithDeviceRequest $request): JsonResponse
    {
        try {
            return response()->json(new AuthTokenRes(($this->handler)($request->getDto())));
        } catch (InvalidCredentialsException $exception) {
            return $this->error($exception->getMessage(), 401);
        } catch (AccountSuspendedException|AccountUnverifiedException|DeviceBlockedException $exception) {
            return $this->error($exception->getMessage(), 403);
        } catch (UnrecognizedDeviceException $exception) {
            return $this->error($exception->getMessage(), 428, ['requires_device_verification' => true]);
        }
    }

    /**
     * @param array<string, bool> $context
     */
    private function error(string $message, int $status, array $context = []): JsonResponse
    {
        return response()->json(['message' => $message] + $context, $status);
    }
}
