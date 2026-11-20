<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\IAM\Infrastructure\Persistence\DeviceModel;
use Src\IAM\Infrastructure\Persistence\UserModel;
use Tests\TestCase;

final class LoginWithDeviceEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_a_trusted_device_can_log_in(): void
    {
        $user = $this->createActiveUser();
        $deviceId = DeviceId::generate();
        $this->createDevice($user->id, $deviceId->value(), 'trusted');

        $this->postJson('/api/v1/auth/login-device', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_id' => $deviceId->value(),
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user_id'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user_id', $user->id);
    }

    public function test_an_unknown_device_requires_device_verification(): void
    {
        $user = $this->createActiveUser();

        $this->postJson('/api/v1/auth/login-device', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_id' => DeviceId::generate()->value(),
        ])
            ->assertStatus(428)
            ->assertJsonPath('requires_device_verification', true);
    }

    public function test_a_blocked_device_is_forbidden(): void
    {
        $user = $this->createActiveUser();
        $deviceId = DeviceId::generate();
        $this->createDevice($user->id, $deviceId->value(), 'blocked');

        $this->postJson('/api/v1/auth/login-device', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_id' => $deviceId->value(),
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Perangkat ini telah diblokir.');
    }

    private function createActiveUser(): UserModel
    {
        $user = new UserModel;
        $user->forceFill([
            'id' => (string) Str::ulid(),
            'name' => 'Device Login User',
            'email' => 'device-login@example.com',
            'password' => Hash::make('correct-password'),
            'account_status' => 'active',
            'failed_login_attempts' => 0,
            'last_login_at' => now(),
        ]);
        $user->save();

        return $user;
    }

    private function createDevice(string $userId, string $deviceId, string $status): void
    {
        DeviceModel::query()->create([
            'id' => $deviceId,
            'user_id' => $userId,
            'status' => $status,
        ]);
    }
}
