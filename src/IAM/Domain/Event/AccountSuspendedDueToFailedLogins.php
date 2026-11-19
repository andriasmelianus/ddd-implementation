<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Event;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\UserId;
use Src\Shared\Framework\Domain\Events\DomainEvent;

/**
 * Domain Event: akun otomatis ditangguhkan karena percobaan login gagal
 * mencapai ambang batas (5 kali).
 *
 * Aturan Bisnis #4: event ini dicatat oleh Aggregate Root
 * {@see User} pada saat transisi status
 * `ACTIVE → SUSPENDED` terjadi, lalu dilepaskan (dipublikasikan) oleh
 * Command Handler **setelah** state berhasil dipersistensi.
 *
 * Event bersifat immutable dan hanya membawa data faktual mengenai apa yang
 * telah terjadi (bukan instruksi).
 */
final class AccountSuspendedDueToFailedLogins extends DomainEvent
{
    /**
     * @param  UserId  $userId  Identitas akun yang ditangguhkan.
     * @param  EmailAddress  $email  Email akun (berguna untuk notifikasi keamanan).
     * @param  int  $failedAttempts  Jumlah percobaan gagal saat penangguhan terjadi.
     */
    public function __construct(
        public readonly UserId $userId,
        public readonly EmailAddress $email,
        public readonly int $failedAttempts,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'iam.account.suspended_due_to_failed_logins';
    }

    /**
     * Representasi primitif event untuk keperluan logging / message broker.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'email' => $this->email->value(),
            'failed_attempts' => $this->failedAttempts,
            'occurred_on' => $this->occurredOn,
        ];
    }
}
