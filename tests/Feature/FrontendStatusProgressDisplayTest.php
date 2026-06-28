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

    public function test_user_dashboard_branch_labels_use_small_branch_wording(): void
    {
        $overview = $this->frontendFile('resources/js/safi/pages/dashboard/Overview.tsx');
        $structure = $this->frontendFile('resources/js/safi/pages/dashboard/Structure.tsx');

        foreach ([$overview, $structure] as $contents) {
            $this->assertStringNotContainsString('слабая', $contents);
            $this->assertStringNotContainsString('Слабая', $contents);
        }

        $this->assertStringContainsString("'малая'", $overview);
        $this->assertStringContainsString('Малая</Badge>', $structure);
    }

    public function test_dashboard_structure_uses_full_tree_payload(): void
    {
        $structure = $this->frontendFile('resources/js/safi/pages/dashboard/Structure.tsx');
        $adminStructure = $this->frontendFile('resources/js/safi/pages/admin/AdminStructure.tsx');
        $treeCanvas = $this->frontendFile('resources/js/safi/components/structure/StructureTreeCanvas.tsx');

        $this->assertStringContainsString('DashboardStructureTree', $structure);
        $this->assertStringContainsString('StructureTreeCanvas', $structure);
        $this->assertStringContainsString('StructureTreeCanvas', $adminStructure);
        $this->assertStringContainsString('normalizeTreeNode(record.tree)', $structure);
        $this->assertStringContainsString('childrenRecord.left', $structure);
        $this->assertStringContainsString('childrenRecord.right', $structure);
        $this->assertStringContainsString('overflow-x-auto overflow-y-auto', $treeCanvas);
        $this->assertStringContainsString('StructureTreeToolbar', $treeCanvas);
        $this->assertStringContainsString('getTreeConnectorPath', $treeCanvas);
        $this->assertStringNotContainsString('Node name="Левая ветка"', $structure);
        $this->assertStringNotContainsString('Node name="Правая ветка"', $structure);
    }

    public function test_dashboard_structure_orders_partner_list_before_collapsed_tree_without_referrals(): void
    {
        $structure = $this->frontendFile('resources/js/safi/pages/dashboard/Structure.tsx');

        $this->assertStringNotContainsString('Реферальные ссылки', $structure);
        $this->assertStringNotContainsString('ReferralBox', $structure);
        $this->assertStringNotContainsString('buildReferralBranchUrl', $structure);
        $this->assertStringNotContainsString('Копировать', $structure);

        $partnerListPosition = strpos($structure, 'Список партнеров');
        $treePosition = strpos($structure, 'Бинарное дерево');

        $this->assertNotFalse($partnerListPosition);
        $this->assertNotFalse($treePosition);
        $this->assertLessThan($treePosition, $partnerListPosition);

        $this->assertStringContainsString('const [isTreeVisible, setIsTreeVisible] = useState(false)', $structure);
        $this->assertStringContainsString("setIsTreeVisible((visible) => !visible)", $structure);
        $this->assertStringContainsString("{isTreeVisible ? 'Скрыть дерево' : 'Показать дерево'}", $structure);
        $this->assertStringContainsString('{isTreeVisible && <DashboardStructureTree', $structure);
    }

    private function frontendFile(string $relativePath): string
    {
        $path = base_path($relativePath);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
