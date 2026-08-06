<?php

namespace Tests\Feature;

use App\Models\ActivationLicense;
use App\Status;
use App\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CreateActivationLicenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('activation_license.api_username', 'test-user');
        config()->set('activation_license.api_password', 'test-password');
        config()->set('activation_license.code_prefix', 'STELLAR');
        config()->set('activation_license.max_batch_size', 100);
    }

    public function test_creation_requires_basic_authentication(): void
    {
        $response = $this->postJson('/api/v1/activationlicensecontroller/create', [
            'type' => Type::VPN->value,
            'subscriptions_days' => 30,
        ]);

        $response
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="Activation License API"')
            ->assertExactJson([
                'response_code' => 401,
                'response_message' => 'Unauthorized.',
            ]);
    }

    public function test_it_creates_an_active_license_with_a_generated_code(): void
    {
        $response = $this->authenticatedPost([
            'type' => Type::VPN->value,
            'subscriptions_days' => 30,
        ]);

        $response
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store')
            ->assertJsonPath('response_code', 201)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('licenses.0.status', Status::ACTIVE->value)
            ->assertJsonPath('licenses.0.type', Type::VPN->value)
            ->assertJsonPath('licenses.0.subscriptions_days', 30);

        $code = $response->json('licenses.0.code');

        $this->assertMatchesRegularExpression('/^STELLAR-[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}$/', $code);

        $this->assertDatabaseHas('activationlicenses', [
            'code' => $code,
            'status' => Status::ACTIVE->value,
            'type' => Type::VPN->value,
            'subscriptions_days' => 30,
        ]);
    }

    public function test_it_generates_a_nine_character_antivirus_code(): void
    {
        $response = $this->authenticatedPost([
            'type' => Type::ANTIVIRUS->value,
            'subscriptions_days' => 365,
        ]);

        $response->assertCreated();

        $code = $response->json('licenses.0.code');

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{9}$/', $code);
        $this->assertSame(9, strlen($code));
    }

    public function test_it_returns_the_same_license_for_an_idempotent_replay(): void
    {
        $payload = [
            'type' => Type::ANTIVIRUS->value,
            'subscriptions_days' => 365,
            'idempotency_key' => 'antivirus:order-123:item-456:unit-1',
        ];

        $firstResponse = $this->authenticatedPost($payload);
        $firstResponse->assertCreated();
        $this->assertMatchesRegularExpression(
            '/^[A-HJ-NP-Z2-9]{9}$/',
            (string) $firstResponse->json('licenses.0.code')
        );

        $secondResponse = $this->authenticatedPost($payload);

        $secondResponse
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store')
            ->assertJsonPath('response_code', 200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('licenses.0.code', $firstResponse->json('licenses.0.code'))
            ->assertJsonPath('licenses.0.idempotency_key', $payload['idempotency_key']);

        $this->assertSame(1, ActivationLicense::query()->count());
    }

    public function test_it_rejects_reusing_an_idempotency_key_with_different_days(): void
    {
        $idempotencyKey = 'antivirus:order-123:item-456:unit-1';

        $this->authenticatedPost([
            'type' => Type::ANTIVIRUS->value,
            'subscriptions_days' => 30,
            'idempotency_key' => $idempotencyKey,
        ])->assertCreated();

        $response = $this->authenticatedPost([
            'type' => Type::ANTIVIRUS->value,
            'subscriptions_days' => 365,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response
            ->assertConflict()
            ->assertExactJson([
                'response_code' => 409,
                'response_message' => 'The idempotency key was already used with different subscription days.',
            ]);
    }

    public function test_it_creates_a_batch_of_unique_licenses(): void
    {
        $response = $this->authenticatedPost([
            'type' => Type::ANTIVIRUS->value,
            'subscriptions_days' => 365,
            'quantity' => 3,
            'prefix' => 'AV',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('count', 3)
            ->assertJsonCount(3, 'licenses');

        $codes = collect($response->json('licenses'))->pluck('code');

        $this->assertCount(3, $codes->unique());
        $this->assertTrue($codes->every(static fn (string $code): bool => str_starts_with($code, 'AV-')));
        $this->assertSame(3, ActivationLicense::query()->count());
    }

    public function test_it_creates_a_license_with_a_normalized_custom_code(): void
    {
        $response = $this->authenticatedPost([
            'type' => Type::PROTECT->value,
            'subscriptions_days' => 90,
            'status' => Status::INACTIVE->value,
            'code' => ' custom-2026-code ',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('licenses.0.code', 'CUSTOM-2026-CODE')
            ->assertJsonPath('licenses.0.status', Status::INACTIVE->value);
    }

    public function test_it_rejects_a_duplicate_custom_code_for_the_same_type(): void
    {
        ActivationLicense::create([
            'code' => 'DUPLICATE-CODE',
            'status' => Status::ACTIVE->value,
            'type' => Type::VPN->value,
            'subscriptions_days' => 30,
        ]);

        $response = $this->authenticatedPost([
            'type' => Type::VPN->value,
            'subscriptions_days' => 60,
            'code' => 'DUPLICATE-CODE',
        ]);

        $response
            ->assertConflict()
            ->assertExactJson([
                'response_code' => 409,
                'response_message' => 'An activation license with this code and type already exists.',
            ]);
    }

    public function test_it_rejects_invalid_creation_parameters(): void
    {
        $response = $this->authenticatedPost([
            'type' => 999,
            'subscriptions_days' => 0,
            'status' => Status::ACTIVATED->value,
            'quantity' => 2,
            'code' => 'CUSTOM-CODE',
            'prefix' => 'VPN',
            'idempotency_key' => 'invalid key with spaces',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('response_code', 422)
            ->assertJsonValidationErrors([
                'type',
                'subscriptions_days',
                'status',
                'quantity',
                'prefix',
                'idempotency_key',
            ]);
    }

    public function test_it_rejects_batch_creation_with_an_idempotency_key(): void
    {
        $response = $this->authenticatedPost([
            'type' => Type::ANTIVIRUS->value,
            'subscriptions_days' => 365,
            'quantity' => 2,
            'idempotency_key' => 'antivirus:order-123:item-456:unit-1',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    private function authenticatedPost(array $payload): TestResponse
    {
        return $this
            ->withBasicAuth('test-user', 'test-password')
            ->postJson('/api/v1/activationlicensecontroller/create', $payload);
    }
}
