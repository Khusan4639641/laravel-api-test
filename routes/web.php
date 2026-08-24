<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/register', '/login');
Route::redirect('/registration', '/login');
Route::redirect('/sign-up', '/login');

Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|storage).*$');
