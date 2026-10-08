<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeJwtBridgeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'shared-test-secret-shared-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['nodeauth.secret' => self::SECRET]);
    }

    private function token(array $claims, string $secret = self::SECRET): string
    {
        return JWT::encode($claims + ['exp' => time() + 600], $secret, 'HS256');
    }

    private function callMe(string $token)
    {
        return $this->getJson('/api/app/me', ['Authorization' => "Bearer {$token}"]);
    }

    public function test_a_valid_node_access_token_identifies_the_user(): void
    {
        $user = User::factory()->create();

        $this->callMe($this->token(['sub' => $user->id, 'token_type' => 'access']))
            ->assertOk()
            ->assertJson(['id' => $user->id]);
    }

    public function test_missing_token_is_401(): void
    {
        $this->getJson('/api/app/me')->assertStatus(401);
    }

    public function test_token_signed_with_another_secret_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->callMe($this->token(['sub' => $user->id, 'token_type' => 'access'], 'a-different-secret-a-different-secret'))
            ->assertStatus(401);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $expired = JWT::encode(['sub' => $user->id, 'token_type' => 'access', 'exp' => time() - 3600], self::SECRET, 'HS256');

        $this->callMe($expired)->assertStatus(401);
    }

    public function test_a_refresh_token_cannot_be_used_as_an_access_token(): void
    {
        $user = User::factory()->create();

        $this->callMe($this->token(['sub' => $user->id, 'token_type' => 'refresh']))->assertStatus(401);
    }

    public function test_token_for_a_deleted_user_is_rejected(): void
    {
        $this->callMe($this->token(['sub' => 9999, 'token_type' => 'access']))->assertStatus(401);
    }

    public function test_unconfigured_bridge_fails_closed(): void
    {
        config(['nodeauth.secret' => '']);

        $this->callMe('anything')->assertStatus(500);
    }
}
