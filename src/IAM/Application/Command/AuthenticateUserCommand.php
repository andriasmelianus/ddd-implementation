<?php

declare(strict_types=1);

namespace Src\IAM\Application\Command;

use Src\IAM\Application\Query\AuthenticateUserHandler;
use Src\IAM\Application\Query\AuthenticateUserQuery;

/**
 * 📌 CATATAN ARSITEKTUR — mengapa kelas ini kosong secara sengaja.
 *
 * Lembar soal praktikum menamai use case login sebagai
 * `AuthenticateUserCommand` + `AuthenticateUserCommandHandler` yang
 * mengembalikan `AuthTokenDto`.
 *
 * Namun Critical Rule #1 proyek ini bersifat **non-negotiable**:
 * > "Commands ALWAYS return void."
 *
 * Login harus mengembalikan access token secara langsung kepada pemanggil, dan
 * token tersebut **tidak dapat** di-query ulang setelah dibuat. Kondisi ini
 * persis merupakan *Special Security/Infrastructure Exception
 * (State-Modifying Query)* yang didokumentasikan pada
 * `docs/core-architecture/02-critical-rules.md`.
 *
 * Karena itu use case diimplementasikan sebagai **Query**, bukan Command:
 *
 * @see AuthenticateUserQuery
 * @see AuthenticateUserHandler
 * @deprecated Gunakan {@see AuthenticateUserQuery}.
 */
final class AuthenticateUserCommand
{
    private function __construct() {}
}
