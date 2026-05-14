<?php

namespace Tests\Feature\PMC\Setting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bank account settings — used by the Receivable detail page to generate
 * VietQR payment QR codes for the "Lịch sử dòng tiền" history entries.
 */
class BankAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const GROUP = 'bank_account';

    private const ENDPOINT = '/api/v1/pmc/settings/bank_account';

    #[Test]
    public function test_can_save_bank_account_settings(): void
    {
        $this->actingAsAdmin();

        $response = $this->putJson(self::ENDPOINT, [
            'settings' => [
                ['key' => 'bank_bin', 'value' => '970422'],
                ['key' => 'bank_name', 'value' => 'MB Bank'],
                ['key' => 'account_number', 'value' => '0123456789'],
                ['key' => 'account_holder', 'value' => 'CONG TY TNHH TNP'],
            ],
        ]);

        $response->assertOk();

        $show = $this->getJson(self::ENDPOINT);
        $show->assertOk()
            ->assertJsonPath('data.bank_bin', '970422')
            ->assertJsonPath('data.account_number', '0123456789')
            ->assertJsonPath('data.account_holder', 'CONG TY TNHH TNP');
    }

    #[Test]
    public function test_rejects_invalid_bank_bin(): void
    {
        $this->actingAsAdmin();

        $response = $this->putJson(self::ENDPOINT, [
            'settings' => [
                ['key' => 'bank_bin', 'value' => 'ABC123'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    #[Test]
    public function test_rejects_non_numeric_account_number(): void
    {
        $this->actingAsAdmin();

        $response = $this->putJson(self::ENDPOINT, [
            'settings' => [
                ['key' => 'account_number', 'value' => '123-ABC'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }
}
