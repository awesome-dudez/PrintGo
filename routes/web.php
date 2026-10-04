<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('kiosk');
});

Route::get('/admin', function () {
    return view('kiosk', ['startView' => 'admin']);
});
