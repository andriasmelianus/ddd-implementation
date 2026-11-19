<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\IAM\Application\Query\AuthenticateUserQuery;

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
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:1'],
        ];
    }

    public function getDto(): AuthenticateUserQuery
    {
        /** @var array{email: string, password: string} $validated */
        $validated = $this->validated();

        return new AuthenticateUserQuery(
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
