<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Entity;

use Src\IAM\Domain\Events\UserAuthenticated;
use Src\IAM\Domain\Events\UserAuthenticationFailed;
use Src\IAM\Domain\Events\UserSuspended;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\Ports\PasswordHasherInterface;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\Entities\BaseAggregateRoot;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * Class User
 *
 * **Aggregate Root** untuk identitas user di dalam bounded context IAM.
 *
 * Mewarisi {@see BaseAggregateRoot} sehingga memiliki kemampuan
 * {@see BaseAggregateRoot::recordEvent()} dan
 * {@see BaseAggregateRoot::pullEvents()}.
 *
 * Seluruh aturan autentikasi dienkapsulasi di dalam entitas ini
 * ({@see self::authenticate()}) sehingga lapisan Application tidak perlu
 * — dan tidak boleh — mengetahui detail aturannya:
 * - pencocokan password,
 * - pengecekan status akun,
 * - increment `failedLoginAttempts`,
 * - auto-suspend pada percobaan ke-{@see self::MAX_FAILED_LOGIN_ATTEMPTS},
 * - reset hitungan ketika login sukses.
 *
 * Invariant dijaga lewat constructor `private` + factory
 * {@see self::create()} (data baru) dan {@see self::reconstitute()}
 * (hidrasi dari database), sesuai critical rule #5.
 */
final class User extends BaseAggregateRoot
{
    /**
     * Jumlah percobaan login gagal berturut-turut yang memicu auto-suspend.
     */
    public const MAX_FAILED_LOGIN_ATTEMPTS = 5;

    /**
     * @param  Ulid  $id  Identitas user.
     * @param  EmailAddress  $email  Email user.
     * @param  string  $hashedPassword  Password yang SUDAH di-hash.
     * @param  AccountStatus  $status  Status siklus hidup akun.
     * @param  int  $failedLoginAttempts  Hitungan percobaan login gagal berturut-turut.
     */
    private function __construct(
        public readonly Ulid $id,
        public readonly EmailAddress $email,
        private string $hashedPassword,
        private AccountStatus $status,
        private int $failedLoginAttempts,
    ) {}

    /**
     * Factory untuk user **baru**.
     *
     * Status awal sengaja {@see AccountStatus::UNVERIFIED} — user wajib
     * memverifikasi email sebelum dapat login.
     *
     * @param  string  $hashedPassword  Password yang SUDAH di-hash oleh
     *                                  {@see PasswordHasherInterface::hash()}.
     *                                  Entitas TIDAK pernah menerima plain
     *                                  password untuk disimpan.
     */
    public static function create(
        Ulid $id,
        EmailAddress $email,
        string $hashedPassword,
    ): self {
        return new self(
            id: $id,
            email: $email,
            hashedPassword: $hashedPassword,
            status: AccountStatus::UNVERIFIED,
            failedLoginAttempts: 0,
        );
    }

    /**
     * Factory untuk **hidrasi dari database** — menerima state apa adanya
     * tanpa mencatat domain event.
     */
    public static function reconstitute(
        Ulid $id,
        EmailAddress $email,
        string $hashedPassword,
        AccountStatus $status,
        int $failedLoginAttempts,
    ): self {
        return new self(
            id: $id,
            email: $email,
            hashedPassword: $hashedPassword,
            status: $status,
            failedLoginAttempts: max(0, $failedLoginAttempts),
        );
    }

    /**
     * Mengautentikasi user dengan password mentah.
     *
     * Urutan pengecekan **disengaja**: password diverifikasi SEBELUM status
     * akun diperiksa. Bila status diperiksa lebih dulu, response 403
     * ("ditangguhkan"/"belum terverifikasi") akan membocorkan bahwa email
     * tersebut terdaftar — celah *user enumeration* yang justru dihindari
     * oleh {@see InvalidCredentialsException} yang pesannya generik.
     *
     * Efek samping bila password salah:
     * - `failedLoginAttempts` bertambah satu,
     * - akun otomatis di-suspend ketika hitungan mencapai
     *   {@see self::MAX_FAILED_LOGIN_ATTEMPTS},
     * - event {@see UserAuthenticationFailed} (dan {@see UserSuspended}
     *   bila ditangguhkan) dicatat,
     * - selalu diakhiri dengan {@see InvalidCredentialsException}.
     *
     * Bila password benar namun status bukan {@see AccountStatus::ACTIVE},
     * hitungan gagal TIDAK bertambah — kredensialnya memang benar — dan
     * guard status melempar exception yang sesuai.
     *
     * @param  string  $plainPassword  Password mentah dari user.
     * @param  PasswordHasherInterface  $hasher  Port verifikasi password.
     *
     * @throws InvalidCredentialsException Bila password tidak cocok.
     * @throws AccountSuspendedException Bila akun ditangguhkan.
     * @throws AccountUnverifiedException Bila akun belum terverifikasi.
     */
    public function authenticate(string $plainPassword, PasswordHasherInterface $hasher): void
    {
        if (! $hasher->verify($plainPassword, $this->hashedPassword)) {
            $this->registerFailedLoginAttempt();

            throw InvalidCredentialsException::create();
        }

        $this->status->ensureCanAuthenticate();

        $this->resetFailedLoginAttempts();

        $this->recordEvent(new UserAuthenticated($this->id->getValue()));
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function failedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function hashedPassword(): string
    {
        return $this->hashedPassword;
    }

    /**
     * Sisa percobaan sebelum akun ditangguhkan (minimum 0).
     */
    public function remainingLoginAttempts(): int
    {
        return max(0, self::MAX_FAILED_LOGIN_ATTEMPTS - $this->failedLoginAttempts);
    }

    /**
     * Mencatat satu percobaan login gagal, sekaligus menangguhkan akun
     * bila ambang batas tercapai.
     *
     * Akun yang sudah {@see AccountStatus::SUSPENDED} tidak lagi menambah
     * hitungan: nilainya dibekukan pada ambang batas agar tidak tumbuh
     * tanpa batas akibat percobaan berulang.
     */
    private function registerFailedLoginAttempt(): void
    {
        if ($this->status === AccountStatus::SUSPENDED) {
            $this->recordEvent(new UserAuthenticationFailed(
                $this->id->getValue(),
                $this->failedLoginAttempts,
            ));

            return;
        }

        $this->failedLoginAttempts++;

        $this->recordEvent(new UserAuthenticationFailed(
            $this->id->getValue(),
            $this->failedLoginAttempts,
        ));

        if ($this->failedLoginAttempts >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
            $this->suspendForTooManyFailedAttempts();
        }
    }

    /**
     * Menangguhkan akun karena percobaan login gagal berlebihan.
     */
    private function suspendForTooManyFailedAttempts(): void
    {
        if (! $this->status->canTransitionTo(AccountStatus::SUSPENDED)) {
            return;
        }

        $this->status = AccountStatus::SUSPENDED;

        $this->recordEvent(new UserSuspended(
            $this->id->getValue(),
            $this->failedLoginAttempts,
        ));
    }

    /**
     * Mengembalikan hitungan percobaan gagal ke nol.
     */
    private function resetFailedLoginAttempts(): void
    {
        $this->failedLoginAttempts = 0;
    }
}
