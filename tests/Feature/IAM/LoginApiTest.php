<?php

declare(strict_types=1);

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\IAM\Domain\ValueObject\AccountStatus;
use Src\IAM\Infrastructure\Persistence\UserModel;
use Tests\TestCase;

final class LoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token_and_resets_failed_attempts(): void
    {
        $userId = (string) Str::ulid();
        UserModel::query()->create([
            'id' => $userId,
            'name' => 'Alice Test',
            'email' => 'alice@example.com',
            'password' => Hash::make('secret123'),
            'account_status' => AccountStatus::ACTIVE->value,
            'failed_login_attempts' => 2,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'user_id',
            ])
            ->assertJson([
                'token_type' => 'Bearer',
                'user_id' => $userId,
            ]);

        $updatedUser = UserModel::query()->find($userId);
        $this->assertSame(0, $updatedUser->failed_login_attempts);
        $this->assertNotNull($updatedUser->last_login_at);
    }

    public function test_login_with_wrong_password_returns_401_and_increments_attempts_in_database(): void
    {
        $userId = (string) Str::ulid();
        UserModel::query()->create([
            'id' => $userId,
            'name' => 'Bob Test',
            'email' => 'bob@example.com',
            'password' => Hash::make('correct-password'),
            'account_status' => AccountStatus::ACTIVE->value,
            'failed_login_attempts' => 1,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'bob@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Kredensial yang diberikan tidak valid.']);

        $updatedUser = UserModel::query()->find($userId);
        $this->assertSame(2, $updatedUser->failed_login_attempts);
        $this->assertSame(AccountStatus::ACTIVE->value, $updatedUser->account_status);
    }

    public function test_fifth_failed_attempt_suspends_account_in_database(): void
    {
        $userId = (string) Str::ulid();
        UserModel::query()->create([
            'id' => $userId,
            'name' => 'Charlie Test',
            'email' => 'charlie@example.com',
            'password' => Hash::make('correct-password'),
            'account_status' => AccountStatus::ACTIVE->value,
            'failed_login_attempts' => 4,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'charlie@example.com',
            'password' => 'wrong-password-again',
        ]);

        $response->assertStatus(401);

        $updatedUser = UserModel::query()->find($userId);
        $this->assertSame(5, $updatedUser->failed_login_attempts);
        $this->assertSame(AccountStatus::SUSPENDED->value, $updatedUser->account_status);
    }

    public function test_login_with_suspended_account_returns_403(): void
    {
        UserModel::query()->create([
            'id' => (string) Str::ulid(),
            'name' => 'Suspended User',
            'email' => 'suspended@example.com',
            'password' => Hash::make('secret123'),
            'account_status' => AccountStatus::SUSPENDED->value,
            'failed_login_attempts' => 5,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Akun Anda sedang ditangguhkan.']);
    }

    public function test_login_with_unverified_account_returns_403(): void
    {
        UserModel::query()->create([
            'id' => (string) Str::ulid(),
            'name' => 'Unverified User',
            'email' => 'unverified@example.com',
            'password' => Hash::make('secret123'),
            'account_status' => AccountStatus::UNVERIFIED->value,
            'failed_login_attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Silakan verifikasi email Anda terlebih dahulu.']);
    }

    public function test_login_with_non_existent_user_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Kredensial yang diberikan tidak valid.']);
    }

    public function test_login_validation_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}

