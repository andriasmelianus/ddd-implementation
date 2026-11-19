<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\Exceptions;

use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

class IamDomainExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<DomainException>, 1: string, 2: int}>
     */
    public static function exceptionProvider(): array
    {
        return [
            'invalid credentials' => [
                InvalidCredentialsException::class,
                'Email atau password yang Anda masukkan salah.',
                401,
            ],
            'account suspended' => [
                AccountSuspendedException::class,
                'Akun Anda sedang ditangguhkan.',
                403,
            ],
            'account unverified' => [
                AccountUnverifiedException::class,
                'Silakan verifikasi email Anda terlebih dahulu.',
                403,
            ],
        ];
    }

    /**
     * @param  class-string<DomainException>  $class
     */
    #[DataProvider('exceptionProvider')]
    public function test_it_carries_the_expected_message_and_code(string $class, string $message, int $code): void
    {
        $exception = $class::create();

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    /**
     * @param  class-string<DomainException>  $class
     */
    #[DataProvider('exceptionProvider')]
    public function test_it_is_a_user_visible_domain_exception(string $class): void
    {
        $exception = $class::create();

        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertInstanceOf(VisibleExceptionInterface::class, $exception);
    }

    /**
     * @param  class-string<DomainException>  $class
     */
    #[DataProvider('exceptionProvider')]
    public function test_it_allows_overriding_the_message(string $class): void
    {
        $exception = new $class('Pesan khusus.');

        $this->assertSame('Pesan khusus.', $exception->getMessage());
    }

    public function test_invalid_credentials_message_does_not_disclose_which_factor_failed(): void
    {
        $message = mb_strtolower(InvalidCredentialsException::MESSAGE);

        $this->assertStringNotContainsString('tidak terdaftar', $message);
        $this->assertStringNotContainsString('tidak ditemukan', $message);
        $this->assertStringContainsString('atau', $message);
    }
}
