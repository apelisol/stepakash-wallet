<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DerivDepositController;

Route::post('/initiate-deposit', [DerivDepositController::class, 'initiateDeposit']);
Route::post('/check-deposit-status', [DerivDepositController::class, 'checkDepositStatus']);
