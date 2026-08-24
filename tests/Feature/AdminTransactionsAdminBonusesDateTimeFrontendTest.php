<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminTransactionsAdminBonusesDateTimeFrontendTest extends TestCase
{
    public function test_admin_transaction_tables_use_shared_updated_at_date_formatter(): void
    {
        $helper = $this->frontendFile('resources/js/safi/lib/adminDateTime.ts');
        $transactionsPage = $this->frontendFile('resources/js/safi/pages/admin/AdminTransactions.tsx');
        $bonusesPage = $this->frontendFile('resources/js/safi/pages/admin/AdminBonuses.tsx');

        $this->assertStringContainsString('export function formatAdminDateTime', $helper);
        $this->assertStringContainsString(".replace('T', ' ')", $helper);
        $this->assertStringContainsString(".replace(/\\.\\d+Z?$/, '')", $helper);
        $this->assertStringContainsString(".replace(/Z$/, '')", $helper);
        $this->assertStringContainsString('.slice(0, 19)', $helper);

        foreach ([$transactionsPage, $bonusesPage] as $page) {
            $this->assertStringContainsString("import { formatAdminDateTime } from '../../lib/adminDateTime';", $page);
            $this->assertStringContainsString("formatAdminDateTime(getString(trx, ['updated_at', 'updatedAt', 'update_date', 'updateDate', 'created_at', 'createdAt']))", $page);
            $this->assertStringNotContainsString("date: getString(trx, ['created_at']) || '-'", $page);
        }
    }

    private function frontendFile(string $relativePath): string
    {
        $path = base_path($relativePath);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
