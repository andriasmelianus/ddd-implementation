<?php

declare(strict_types=1);

namespace Src\IAM\UI\Request;

use Illuminate\Foundation\Http\FormRequest;
use Src\IAM\Application\Command\AuthenticateUserCommand;

final class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function getDto(): AuthenticateUserCommand
    {
        return new AuthenticateUserCommand(
            email: (string) $this->input('email'),
            password: (string) $this->input('password'),
        );
    }
}

