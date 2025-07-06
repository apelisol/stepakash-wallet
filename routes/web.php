<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/derivdeposit', function () {
    return view('derivdeposit');
});
