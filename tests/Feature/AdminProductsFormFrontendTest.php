<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminProductsFormFrontendTest extends TestCase
{
    public function test_admin_product_form_does_not_render_or_submit_manual_pv_field(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/safi/pages/admin/AdminProducts.tsx'));

        $this->assertStringNotContainsString("payload.append('pv'", $source);
        $this->assertStringNotContainsString('label="PV"', $source);
        $this->assertStringNotContainsString('form.pv', $source);
    }

    public function test_admin_product_form_renders_and_submits_deposit_only_checkbox(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/safi/pages/admin/AdminProducts.tsx'));

        $this->assertStringContainsString('Только за депозит', $source);
        $this->assertStringContainsString("payload.append('is_deposit_product'", $source);
        $this->assertStringContainsString('isDepositProduct: Boolean', $source);
        $this->assertStringContainsString('Тип оплаты', $source);
        $this->assertStringContainsString('Только депозит', $source);
        $this->assertStringContainsString('Обычный', $source);
    }
}
