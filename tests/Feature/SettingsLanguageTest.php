<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_return_localized_values_when_setting_contains_translations(): void
    {
        SystemSetting::query()->create([
            'key' => 'company.tagline',
            'value' => [
                'ru' => 'Русский слоган',
                'en' => 'English tagline',
            ],
            'type' => 'array',
            'group' => 'company',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $settings = $this->getJson('/api/admin/settings', ['Accept-Language' => 'en'])
            ->assertOk()
            ->json('settings');

        $this->assertSame('English tagline', $settings['company.tagline']);
    }
}
