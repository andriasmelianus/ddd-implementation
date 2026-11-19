<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Entity;

use DateTimeImmutable;
use Src\IAM\Domain\Event\AccountSuspendedDueToFailedLogins;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\HashedPassword;
use Src\IAM\Domain\ValueObjects\UserId;
use Src\Shared\Framework\Domain\Entities\BaseAggregateRoot;

/**
 * Aggregate Root `User` — penjaga seluruh invariant autentikasi.
 *
 * Kelas ini adalah **satu-satunya** tempat aturan bisnis login #3, #4, dan #5
 * dieksekusi. Command Handler hanya mengorkestrasi (load → panggil behavior →
 * save → publish event), sementara keputusan bisnis sepenuhnya ada di sini.
 *
 * 🚨 Critical Rule #5 (Invariant Entity):
 * - Konstruktor bersifat `private` → instance tidak dapat dibuat sembarangan.
 * - Hanya dua jalur pembuatan: {@see self::create()} untuk akun baru dan
 *   {@see self::reconstitute()} untuk hidrasi dari persistensi.
 * - **Tanpa reflection** — seluruh properti diisi melalui konstruktor.
 * - Properti bersifat `private` dengan getter eksplisit sehingga state tidak
 *   dapat dimutasi dari luar; perubahan hanya terjadi lewat method bisnis.
 *
 * Alur `authenticate()` mengikuti urutan pengecekan yang diwajibkan:
 * 1. Status akun (SUSPENDED / UNVERIFIED) → ditolak lebih dulu.
 * 2. Pencocokan password via port hasher.
 * 3. Bila gagal → increment counter, auto-suspend pada percobaan ke-5, rekam event.
 * 4. Bila berhasil → reset counter & perbarui `last_login_at`.
 */
final class User extends BaseAggregateRoot
{
    /**
     * Ambang batas percobaan login gagal sebelum akun ditangguhkan otomatis.
     *
     * Aturan Bisnis #4: bila `failedLoginAttempts >= 5`, status berubah
     * menjadi {@see AccountStatus::SUSPENDED}.
     */
    public const int MAX_FAILED_LOGIN_ATTEMPTS = 5;

    /**
     * Konstruktor privat — menegakkan pembuatan objek hanya via factory method.
     */
    private function __construct(
        private readonly UserId $id,
        private string $name,
        private EmailAddress $email,
        private HashedPassword $password,
        private AccountStatus $accountStatus,
        private int $failedLoginAttempts,
        private ?DateTimeImmutable $lastLoginAt,
        private ?DateTimeImmutable $emailVerifiedAt,
    ) {}

    /**
     * Factory method untuk **akun baru**.
     *
     * State awal: status `UNVERIFIED`, nol percobaan gagal, belum pernah login.
     */
    public static function create(
        UserId $id,
        string $name,
        EmailAddress $email,
        HashedPassword $password,
        AccountStatus $accountStatus = AccountStatus::UNVERIFIED,
    ): self {
        $user = new self(
            id: $id,
            name: $name,
            email: $email,
            password: $password,
            accountStatus: $accountStatus,
            failedLoginAttempts: 0,
            lastLoginAt: null,
            emailVerifiedAt: null,
        );

        $user->setIsNew(true);

        return $user;
    }

    /**
     * Factory method untuk **hidrasi dari persistensi** (tanpa reflection).
     *
     * Dipanggil oleh Infrastructure Layer untuk membangun kembali Aggregate
     * Root dari baris database. Tidak memicu domain event karena ini bukan
     * perubahan state bisnis, hanya pemulihan state yang sudah ada.
     */
    public static function reconstitute(
        UserId $id,
        string $name,
        EmailAddress $email,
        HashedPassword $password,
        AccountStatus $accountStatus,
        int $failedLoginAttempts,
        ?DateTimeImmutable $lastLoginAt,
        ?DateTimeImmutable $emailVerifiedAt,
    ): self {
        $user = new self(
            id: $id,
            name: $name,
            email: $email,
            password: $password,
            accountStatus: $accountStatus,
            failedLoginAttempts: max(0, $failedLoginAttempts),
            lastLoginAt: $lastLoginAt,
            emailVerifiedAt: $emailVerifiedAt,
        );

        $user->setIsNew(false);

        return $user;
    }

    /**
     * Menjalankan seluruh invariant autentikasi login.
     *
     * Method ini **memutasi** state agregat (counter percobaan gagal, status
     * akun, `last_login_at`) sehingga pemanggil **wajib** mempersistensi
     * agregat setelahnya — termasuk pada jalur kegagalan password, agar
     * proteksi brute force benar-benar tercatat.
     *
     * @param  string  $plainPassword  Password mentah dari input pengguna.
     * @param  PasswordHasherInterface  $passwordHasher  Port verifikasi password.
     * @param  DateTimeImmutable  $occurredAt  Waktu percobaan login (injected agar deterministik saat diuji).
     *
     * @throws AccountSuspendedException Bila status akun `SUSPENDED`.
     * @throws AccountUnverifiedException Bila status akun `UNVERIFIED`.
     * @throws InvalidCredentialsException Bila password tidak cocok.
     */
    public function authenticate(
        string $plainPassword,
        PasswordHasherInterface $passwordHasher,
        DateTimeImmutable $occurredAt,
    ): void {
        // Aturan #3: status akun diperiksa SEBELUM password dicocokkan.
        $this->ensureAccountCanAuthenticate();

        // Aturan #4: pencocokan password melalui port hasher.
        if (! $passwordHasher->verify($plainPassword, $this->password)) {
            $this->registerFailedLoginAttempt();

            throw InvalidCredentialsException::passwordMismatch();
        }

        // Aturan #5: login berhasil.
        $this->registerSuccessfulLogin($occurredAt);
    }

    /**
     * Aturan Bisnis #3 — memastikan status akun mengizinkan autentikasi.
     *
     * @throws AccountSuspendedException
     * @throws AccountUnverifiedException
     */
    public function ensureAccountCanAuthenticate(): void
    {
        if ($this->accountStatus->isSuspended()) {
            throw AccountSuspendedException::create();
        }

        if ($this->accountStatus->isUnverified()) {
            throw AccountUnverifiedException::create();
        }
    }

    /**
     * Aturan Bisnis #4 — mencatat percobaan login gagal.
     *
     * Menaikkan counter satu langkah. Bila counter mencapai ambang batas,
     * akun otomatis ditangguhkan dan Domain Event
     * {@see AccountSuspendedDueToFailedLogins} direkam.
     */
    public function registerFailedLoginAttempt(): void
    {
        $this->failedLoginAttempts++;

        if ($this->failedLoginAttempts < self::MAX_FAILED_LOGIN_ATTEMPTS) {
            return;
        }

        // Idempoten: hanya melakukan transisi sekali, event tidak terduplikasi.
        if ($this->accountStatus->isSuspended()) {
            return;
        }

        $this->accountStatus = AccountStatus::SUSPENDED;

        $this->recordEvent(new AccountSuspendedDueToFailedLogins(
            userId: $this->id,
            email: $this->email,
            failedAttempts: $this->failedLoginAttempts,
        ));
    }

    /**
     * Aturan Bisnis #5 — mencatat login sukses: reset counter & perbarui waktu login.
     */
    public function registerSuccessfulLogin(DateTimeImmutable $occurredAt): void
    {
        $this->failedLoginAttempts = 0;
        $this->lastLoginAt = $occurredAt;
    }

    /**
     * Menandai email terverifikasi sehingga akun menjadi aktif.
     */
    public function verifyEmail(DateTimeImmutable $verifiedAt): void
    {
        if ($this->emailVerifiedAt !== null) {
            return;
        }

        $this->emailVerifiedAt = $verifiedAt;

        if ($this->accountStatus->isUnverified()) {
            $this->accountStatus = AccountStatus::ACTIVE;
        }
    }

    /**
     * Sisa percobaan login sebelum akun ditangguhkan.
     */
    public function remainingLoginAttempts(): int
    {
        return max(0, self::MAX_FAILED_LOGIN_ATTEMPTS - $this->failedLoginAttempts);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function accountStatus(): AccountStatus
    {
        return $this->accountStatus;
    }

    public function failedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }
}
