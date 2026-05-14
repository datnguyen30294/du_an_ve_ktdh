<?php

namespace Tests\Modules\Platform;

use App\Modules\Platform\Auth\Models\RequesterAccount;
use App\Modules\Platform\Setting\ExternalServices\PlatformBankInfoExternalServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformBankAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/platform/settings/bank_account';

    private RequesterAccount $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requester = RequesterAccount::create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    public function test_can_save_and_retrieve_platform_bank_account(): void
    {
        $response = $this->actingAs($this->requester, 'requester')
            ->putJson(self::ENDPOINT, [
                'settings' => [
                    ['key' => 'bank_bin', 'value' => '970422'],
                    ['key' => 'bank_name', 'value' => 'MB Bank'],
                    ['key' => 'account_number', 'value' => '0123456789'],
                    ['key' => 'account_holder', 'value' => 'CONG TY TNHH PLATFORM'],
                ],
            ]);

        $response->assertOk();

        $show = $this->actingAs($this->requester, 'requester')
            ->getJson(self::ENDPOINT);

        $show->assertOk()
            ->assertJsonPath('data.bank_bin', '970422')
            ->assertJsonPath('data.account_number', '0123456789')
            ->assertJsonPath('data.account_holder', 'CONG TY TNHH PLATFORM');
    }

    public function test_rejects_invalid_bank_bin(): void
    {
        $response = $this->actingAs($this->requester, 'requester')
            ->putJson(self::ENDPOINT, [
                'settings' => [
                    ['key' => 'bank_bin', 'value' => 'ABC123'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_rejects_non_numeric_account_number(): void
    {
        $response = $this->actingAs($this->requester, 'requester')
            ->putJson(self::ENDPOINT, [
                'settings' => [
                    ['key' => 'account_number', 'value' => '123-ABC'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_external_service_returns_null_when_unset(): void
    {
        $service = app(PlatformBankInfoExternalServiceInterface::class);

        $this->assertNull($service->getPlatformBankInfo());
    }

    public function test_external_service_returns_shape_compatible_with_frontend(): void
    {
        $this->actingAs($this->requester, 'requester')
            ->putJson(self::ENDPOINT, [
                'settings' => [
                    ['key' => 'bank_bin', 'value' => '970422'],
                    ['key' => 'bank_name', 'value' => 'MB Bank'],
                    ['key' => 'account_number', 'value' => '0123456789'],
                    ['key' => 'account_holder', 'value' => 'CONG TY TNHH PLATFORM'],
                ],
            ])->assertOk();

        $service = app(PlatformBankInfoExternalServiceInterface::class);
        $info = $service->getPlatformBankInfo();

        $this->assertSame([
            'bin' => '970422',
            'label' => 'MB Bank',
            'account_number' => '0123456789',
            'account_name' => 'CONG TY TNHH PLATFORM',
        ], $info);
    }
}
