<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use App\Modules\PMC\Setting\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/settings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // ==================== SERVICE ====================

    public function test_get_returns_default_when_setting_not_exists(): void
    {
        /** @var SystemSettingServiceInterface $service */
        $service = app(SystemSettingServiceInterface::class);

        $value = $service->get('og_ticket', 'sla_quote_minutes', 60);

        $this->assertEquals(60, $value);
    }

    public function test_get_returns_stored_value(): void
    {
        SystemSetting::query()->create([
            'group' => 'og_ticket',
            'key' => 'sla_quote_minutes',
            'value' => '120',
        ]);

        /** @var SystemSettingServiceInterface $service */
        $service = app(SystemSettingServiceInterface::class);

        $this->assertEquals('120', $service->get('og_ticket', 'sla_quote_minutes', 60));
    }

    public function test_get_group_returns_all_settings_for_group(): void
    {
        SystemSetting::query()->create(['group' => 'og_ticket', 'key' => 'sla_quote_minutes', 'value' => '60']);
        SystemSetting::query()->create(['group' => 'og_ticket', 'key' => 'sla_completion_minutes', 'value' => '1440']);
        SystemSetting::query()->create(['group' => 'other', 'key' => 'some_key', 'value' => 'val']);

        /** @var SystemSettingServiceInterface $service */
        $service = app(SystemSettingServiceInterface::class);

        $group = $service->getGroup('og_ticket');

        $this->assertCount(2, $group);
        $this->assertEquals('60', $group['sla_quote_minutes']);
        $this->assertEquals('1440', $group['sla_completion_minutes']);
    }

    public function test_update_group_creates_new_settings(): void
    {
        /** @var SystemSettingServiceInterface $service */
        $service = app(SystemSettingServiceInterface::class);

        $service->updateGroup('og_ticket', [
            'sla_quote_minutes' => '90',
            'sla_completion_minutes' => '2880',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'group' => 'og_ticket',
            'key' => 'sla_quote_minutes',
            'value' => '90',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'group' => 'og_ticket',
            'key' => 'sla_completion_minutes',
            'value' => '2880',
        ]);
    }

    public function test_update_group_upserts_existing_settings(): void
    {
        SystemSetting::query()->create(['group' => 'og_ticket', 'key' => 'sla_quote_minutes', 'value' => '60']);

        /** @var SystemSettingServiceInterface $service */
        $service = app(SystemSettingServiceInterface::class);

        $service->updateGroup('og_ticket', ['sla_quote_minutes' => '120']);

        $this->assertDatabaseHas('system_settings', [
            'group' => 'og_ticket',
            'key' => 'sla_quote_minutes',
            'value' => '120',
        ]);
        $this->assertDatabaseCount('system_settings', 1);
    }

    // ==================== API: GET ====================

    public function test_api_get_settings_by_group(): void
    {
        SystemSetting::query()->create(['group' => 'og_ticket', 'key' => 'sla_quote_minutes', 'value' => '60']);
        SystemSetting::query()->create(['group' => 'og_ticket', 'key' => 'sla_completion_minutes', 'value' => '1440']);

        $response = $this->getJson("{$this->baseUrl}/og_ticket");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sla_quote_minutes', '60')
            ->assertJsonPath('data.sla_completion_minutes', '1440');
    }

    public function test_api_get_returns_404_for_unknown_group(): void
    {
        $response = $this->getJson("{$this->baseUrl}/nonexistent");

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INVALID_SETTING_GROUP');
    }

    // ==================== API: PUT ====================

    public function test_api_update_settings(): void
    {
        $response = $this->putJson("{$this->baseUrl}/og_ticket", [
            'settings' => [
                ['key' => 'sla_quote_minutes', 'value' => '90'],
                ['key' => 'sla_completion_minutes', 'value' => '2880'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('system_settings', [
            'group' => 'og_ticket',
            'key' => 'sla_quote_minutes',
            'value' => '90',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'group' => 'og_ticket',
            'key' => 'sla_completion_minutes',
            'value' => '2880',
        ]);
    }

    public function test_api_update_requires_settings_array(): void
    {
        $response = $this->putJson("{$this->baseUrl}/og_ticket", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_api_update_requires_key_in_each_setting(): void
    {
        $response = $this->putJson("{$this->baseUrl}/og_ticket", [
            'settings' => [
                ['value' => '90'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.key']);
    }

    public function test_api_update_accepts_integer_values_for_og_ticket(): void
    {
        $response = $this->putJson("{$this->baseUrl}/og_ticket", [
            'settings' => [
                ['key' => 'sla_quote_minutes', 'value' => 90],
                ['key' => 'sla_completion_minutes', 'value' => 2880],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('system_settings', [
            'group' => 'og_ticket',
            'key' => 'sla_quote_minutes',
            'value' => '90',
        ]);
    }

    public function test_api_update_rejects_non_integer_for_og_ticket_sla(): void
    {
        $response = $this->putJson("{$this->baseUrl}/og_ticket", [
            'settings' => [
                ['key' => 'sla_quote_minutes', 'value' => 'abc'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_api_update_rejects_zero_or_negative_for_og_ticket_sla(): void
    {
        $response = $this->putJson("{$this->baseUrl}/og_ticket", [
            'settings' => [
                ['key' => 'sla_quote_minutes', 'value' => 0],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }
}
