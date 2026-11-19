<?php

declare(strict_types=1);

namespace Src\IAM\Application\Query;

use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryInterface;

/**
 * Query autentikasi pengguna.
 *
 * ⚠️ **ARCHITECTURAL EXCEPTION (State-Modifying Query)**
 * Operasi ini memutasi state sistem (reset/increment `failed_login_attempts`,
 * `last_login_at`, kemungkinan penangguhan akun) **dan** menghasilkan access
 * token yang harus langsung dikembalikan kepada pemanggil.
 *
 * Sesuai Critical Rule #1, Command **wajib** mengembalikan `void`, sehingga
 * use case ini dimodelkan sebagai **Query** — bukan Command — persis seperti
 * pengecualian resmi yang didokumentasikan untuk operasi keamanan
 * (pembuatan JWT/OTP). Tanpa pengecualian ini, token tidak mungkin diperoleh
 * karena token tidak dapat di-query ulang setelah dibuat.
 *
 * @see AuthenticateUserHandler
 */
final readonly class AuthenticateUserQuery implements QueryInterface
{
    /**
     * @param  EmailAddress  $email  Email yang sudah tervalidasi sintaksisnya oleh Value Object (Aturan Bisnis #1).
     * @param  string  $plainPassword  Password mentah; verifikasi dilakukan oleh port hasher di Domain Layer (Aturan Bisnis #4).
     */
    public function __construct(
        public EmailAddress $email,
        public string $plainPassword,
    ) {}
}
