<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendStatusProgressDisplayTest extends TestCase
{
    public function test_dashboard_status_progress_pages_use_weak_leg_labels(): void
    {
        $overview = $this->frontendFile('resources/js/safi/pages/dashboard/Overview.tsx');
        $packageStatus = $this->frontendFile('resources/js/safi/pages/dashboard/PackageStatus.tsx');
        $bonuses = $this->frontendFile('resources/js/safi/pages/dashboard/Bonuses.tsx');

        foreach ([$overview, $packageStatus, $bonuses] as $contents) {
            $this->assertStringContainsString('Малая ветка PV', $contents);
            $this->assertStringNotContainsString('Ваш PV', $contents);
            $this->assertStringNotContainsString('current={currentUser.personalPV}', $contents);
            $this->assertStringContainsString('current={weakLegPV}', $contents);
        }

        $this->assertStringNotContainsString('Общий PV', $overview);
        $this->assertStringContainsString('Личный PV', $packageStatus);
        $this->assertStringContainsString('label="Прогресс"', $bonuses);
        $this->assertStringContainsString('currentUser.personalPV', $bonuses);
        $this->assertStringNotContainsString('label="Личный PV"', $bonuses);
    }

    public function test_structure_pages_show_explicit_personal_team_and_weak_leg_pv_labels(): void
    {
        $dashboardStructure = $this->frontendFile('resources/js/safi/pages/dashboard/Structure.tsx');
        $adminPartnerDetail = $this->frontendFile('resources/js/safi/pages/admin/AdminPartnerDetail.tsx');
        $adminStructure = $this->frontendFile('resources/js/safi/pages/admin/AdminStructure.tsx');

        foreach ([$dashboardStructure, $adminPartnerDetail, $adminStructure] as $contents) {
            $this->assertStringContainsString('Личный PV', $contents);
            $this->assertStringContainsString('Командный PV', $contents);
            $this->assertStringContainsString('Малая ветка PV', $contents);
        }
    }

    private function frontendFile(string $relativePath): string
    {
        $path = base_path($relativePath);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
