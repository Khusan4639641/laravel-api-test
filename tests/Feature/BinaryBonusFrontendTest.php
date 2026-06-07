<?php

namespace Tests\Feature;

use Tests\TestCase;

class BinaryBonusFrontendTest extends TestCase
{
    public function test_binary_pending_cards_and_admin_calculation_button_are_present(): void
    {
        $overview = $this->frontendFile('resources/js/safi/pages/dashboard/Overview.tsx');
        $bonuses = $this->frontendFile('resources/js/safi/pages/dashboard/Bonuses.tsx');
        $adminBonuses = $this->frontendFile('resources/js/safi/pages/admin/AdminBonuses.tsx');
        $adminPartnerDetail = $this->frontendFile('resources/js/safi/pages/admin/AdminPartnerDetail.tsx');

        $this->assertStringContainsString('Бинар в ожидании', $overview);
        $this->assertStringContainsString('Бинар в ожидании', $bonuses);
        $this->assertStringContainsString('Запустить бинарный расчёт', $adminBonuses);
        $this->assertStringContainsString('canCalculateBinary', $adminPartnerDetail);
    }

    public function test_dashboard_user_pages_do_not_expose_binary_recalculation_button(): void
    {
        foreach ([
            'resources/js/safi/pages/dashboard/Overview.tsx',
            'resources/js/safi/pages/dashboard/Bonuses.tsx',
            'resources/js/safi/pages/dashboard/PackageStatus.tsx',
            'resources/js/safi/pages/dashboard/Structure.tsx',
        ] as $relativePath) {
            $contents = $this->frontendFile($relativePath);

            $this->assertStringNotContainsString('Рассчитать бинар', $contents);
            $this->assertStringNotContainsString('Запустить бинарный расчёт', $contents);
            $this->assertStringNotContainsString('calculateAdminBinary', $contents);
        }
    }

    public function test_old_fourteen_day_bonus_copy_is_replaced_with_binary_15_day_copy(): void
    {
        foreach ([
            'resources/js/safi/pages/HomePage.tsx',
            'resources/js/safi/pages/BusinessPage.tsx',
            'resources/js/safi/pages/dashboard/Bonuses.tsx',
            'resources/js/safi/data/faq.ts',
            'resources/js/safi/i18n/runtimeTranslations.ts',
        ] as $relativePath) {
            $contents = $this->frontendFile($relativePath);

            $this->assertStringNotContainsString('Выплаты каждые 14 дней', $contents);
            $this->assertStringNotContainsString('Выплаты бонусов', $contents);
            $this->assertStringNotContainsString('Плановый период выплат - каждые 14 дней', $contents);
            $this->assertStringContainsString('15 дней', $contents);
        }
    }

    private function frontendFile(string $relativePath): string
    {
        $path = base_path($relativePath);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
