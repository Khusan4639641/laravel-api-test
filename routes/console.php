<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('safi:binary-recalculate-all --scheduled')
    ->monthlyOn(1, '03:00')
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping()
    ->createMutexNameUsing('safi-binary-recalculate-all');

Schedule::command('safi:binary-recalculate-all --scheduled')
    ->monthlyOn(15, '03:00')
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping()
    ->createMutexNameUsing('safi-binary-recalculate-all');
