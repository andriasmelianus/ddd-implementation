<?php

declare(strict_types=1);

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Src\IAM\Infrastructure\Persistence\UserModel;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

/**
 * End-to-end test for `POST /api/v1/auth/login`.
 *
 * Validates the full adapter chain — route, FormRequest, Action, QueryBus,
 * Handler, Eloquent repository, hydrator, resource and the domain-exception
 * to HTTP-status mapping registered in bootstrap/app.php.
 */
final class LoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/auth/login';

    private const string PASSWORD = 'secret123';

    #[Test]
    public function it_issues_a_bearer_token_for_valid_credentials(): void
    {
        $user = $this->seedUser(['account_status' => 'active']);

        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'user@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user_id'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user_id', $user->getKey());

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'failed_login_attempts' => 0,
        ]);

        self::assertNotNull(UserModel::query()->whereKey($user->getKey())->value('last_login_at'));
    }

    #[Test]
    public function it_returns_401_and_increments_the_counter_for_a_wrong_password(): void
    {
        $user = $this->seedUser(['account_status' => 'active']);

        $this->postJson(self::ENDPOINT, [
            'email' => 'user@example.com',
            'password' => 'totally-wrong',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau kata sandi yang Anda masukkan salah.');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'failed_login_attempts' => 1,
        ]);
    }

    #[Test]
    public function the_fifth_consecutive_failure_auto_suspends_the_account(): void
    {
        $user = $this->seedUser(['account_status' => 'active', 'failed_login_attempts' => 4]);

        $this->postJson(self::ENDPOINT, [
            'email' => 'user@example.com',
            'password' => 'totally-wrong',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'failed_login_attempts' => 5,
            'account_status' => 'suspended',
        ]);

        // Even the correct password is now refused, with a distinct 403.
        $this->postJson(self::ENDPOINT, [
            'email' => 'user@example.com',
            'password' => self::PASSWORD,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Akun Anda sedang ditangguhkan.');
    }

    #[Test]
    public function it_returns_403_for_an_unverified_account(): void
    {
        $this->seedUser(['account_status' => 'unverified', 'email_verified_at' => null]);

        $this->postJson(self::ENDPOINT, [
            'email' => 'user@example.com',
            'password' => self::PASSWORD,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Silakan verifikasi email Anda terlebih dahulu.');
    }

    #[Test]
    public function it_returns_401_for_an_unregistered_email(): void
    {
        $this->seedUser(['account_status' => 'active']);

        $this->postJson(self::ENDPOINT, [
            'email' => 'ghost@example.com',
            'password' => self::PASSWORD,
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau kata sandi yang Anda masukkan salah.');
    }

    #[Test]
    public function it_returns_422_for_a_malformed_email(): void
    {
        $this->postJson(self::ENDPOINT, [
            'email' => 'not-an-email',
            'password' => self::PASSWORD,
        ])->assertStatus(422);
    }

    #[Test]
    public function it_returns_422_when_required_fields_are_missing(): void
    {
        $this->postJson(self::ENDPOINT, [])->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedUser(array $overrides = []): UserModel
    {
        return UserModel::query()->create(array_merge([
            'id' => (string) new Ulid,
            'name' => 'Dimas Islami',
            'email' => 'user@example.com',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'account_status' => 'active',
            'failed_login_attempts' => 0,
            'last_login_at' => null,
        ], $overrides));
    }
}
