<?php

declare(strict_types=1);

namespace Src\IAM\UI\Request;

use Features\Shared\Http\AbstractFormRequest;
use Src\IAM\Domain\ValueObjects\EmailAddress;

/**
 * FormRequest untuk `POST /api/v1/auth/login`.
 *
 * 🚨 Critical Rule #7 (Typed Requests): kelas ini punya dua tanggung jawab —
 * `rules()` untuk validasi format HTTP, dan `getDto()` untuk memetakan input
 * menjadi DTO bertipe kuat.
 *
 * ⚠️ Batas tanggung jawab: **tidak ada satu pun aturan bisnis di sini.**
 * Validasi `email` pada `rules()` murni pencegahan dini tingkat HTTP; sumber
 * kebenaran validitas email tetap Value Object {@see EmailAddress} yang
 * dibentuk pada `getDto()`. Pengecekan status akun, pencocokan password, dan
 * proteksi brute force seluruhnya berada di Domain Layer.
 */
final class LoginRequest extends AbstractFormRequest
{
    /**
     * Validasi sintaksis/format tingkat HTTP.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }

    /**
     * Memetakan input tervalidasi menjadi DTO bertipe kuat.
     *
     * Aturan Bisnis #1: pembentukan {@see EmailAddress} di sini akan melempar
     * `InvalidEmailException` bila format email tidak valid.
     */
    public function getDto(): LoginDto
    {
        return new LoginDto(
            email: EmailAddress::fromString($this->getHelper()->getString('email')),
            password: $this->getHelper()->getString('password'),
        );
    }
}
