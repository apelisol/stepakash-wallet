<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DerivController;

Route::prefix('deriv')->group(function () {
    Route::post('/deposit', [DerivController::class, 'deposit']);
    Route::post('/withdraw', [DerivController::class, 'withdraw']);
    Route::post('/process-deposit', [DerivController::class, 'processDeposit']);
    Route::post('/process-withdrawal', [DerivController::class, 'processWithdrawal']);
    Route::get('/transactions/{wallet_id}', [DerivController::class, 'getTransactions']);
});
