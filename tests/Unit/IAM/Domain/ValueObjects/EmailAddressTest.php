<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Common\Exceptions\InvalidEmailException;

class EmailAddressTest extends TestCase
{
    public function test_it_accepts_a_valid_email_address(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        $this->assertSame('user@example.com', $email->value());
        $this->assertSame('user@example.com', (string) $email);
    }

    public function test_from_string_returns_the_iam_value_object(): void
    {
        $this->assertInstanceOf(EmailAddress::class, EmailAddress::fromString('user@example.com'));
    }

    public function test_it_trims_surrounding_whitespace(): void
    {
        $this->assertSame('user@example.com', EmailAddress::fromString("  user@example.com\n")->value());
    }

    public function test_it_lowercases_the_domain_but_preserves_the_local_part(): void
    {
        $email = EmailAddress::fromString('User.Name@EXAMPLE.COM');

        $this->assertSame('User.Name@example.com', $email->value());
        $this->assertSame('User.Name', $email->localPart());
        $this->assertSame('example.com', $email->domain());
    }

    public function test_it_is_equal_to_another_instance_with_the_same_normalised_value(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        $this->assertTrue($email->equals(EmailAddress::fromString('user@EXAMPLE.com')));
        $this->assertFalse($email->equals(EmailAddress::fromString('other@example.com')));
    }

    public function test_it_rejects_an_empty_value(): void
    {
        $this->expectException(InvalidEmailException::class);

        EmailAddress::fromString('   ');
    }

    public function test_it_rejects_a_malformed_value(): void
    {
        $this->expectException(InvalidEmailException::class);

        EmailAddress::fromString('not-an-email');
    }

    public function test_it_rejects_a_value_longer_than_the_maximum_length(): void
    {
        $this->expectException(InvalidEmailException::class);

        EmailAddress::fromString(str_repeat('a', 250).'@example.com');
    }
}
