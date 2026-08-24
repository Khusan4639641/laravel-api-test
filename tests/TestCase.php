<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Legacy MLM tests still exercise package business effects through the public endpoints.
        config(['safi.public_registration_enabled' => true]);
        config(['safi.user_package_changes_enabled' => true]);
        config(['safi.user_package_purchases_enabled' => true]);
    }
}
