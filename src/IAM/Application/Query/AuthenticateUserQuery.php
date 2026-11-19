<?php

declare(strict_types=1);

namespace Src\IAM\Application\Query;

use Src\IAM\Application\DTO\AuthTokenDto;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryInterface;

/**
 * Class AuthenticateUserQuery
 *
 * @see AuthenticateUserHandler
 *
 * ⚠️ **ARCHITECTURAL EXCEPTION (critical rule #1)**: Query ini MENGUBAH
 * state sistem (increment percobaan login gagal, auto-suspend, reset
 * hitungan, lalu menerbitkan token) — sesuatu yang normalnya merupakan
 * tugas Command.
 *
 * Dimodelkan sebagai Query karena token hasil autentikasi WAJIB langsung
 * dikembalikan ke caller dan tidak dapat di-query terpisah. Memakai
 * Command tidak mungkin: `CommandBusInterface::dispatch()` bertipe
 * `void`, sehingga DTO yang dikembalikan handler akan hilang begitu saja.
 * Pengecualian untuk operasi keamanan seperti JWT/OTP ini dinyatakan
 * eksplisit pada critical rule #1.
 *
 * DTO ini immutable dan hanya membawa input mentah dari lapisan HTTP.
 * Validasi format (mis. email wajib valid) adalah tanggung jawab
 * FormRequest; Query hanya meneruskan nilainya.
 *
 * @implements QueryInterface<AuthTokenDto>
 */
final readonly class AuthenticateUserQuery implements QueryInterface
{
    /**
     * @param  string  $email  Email yang diklaim user.
     * @param  string  $password  Password mentah yang diklaim user.
     */
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
