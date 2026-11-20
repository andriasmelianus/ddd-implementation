<?php

declare(strict_types=1);

namespace Src\IAM\UI\Request;

use Illuminate\Foundation\Http\FormRequest;
use Src\IAM\Application\Command\AuthenticateWithDeviceCommand;

final class LoginWithDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:1'],
            'device_id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/i'],
        ];
    }

    public function getDto(): AuthenticateWithDeviceCommand
    {
        /** @var array{email: string, password: string, device_id: string} $validated */
        $validated = $this->validated();

        return new AuthenticateWithDeviceCommand(
            email: $validated['email'],
            password: $validated['password'],
            deviceId: $validated['device_id'],
            ipAddress: (string) $this->ip(),
        );
    }
}
