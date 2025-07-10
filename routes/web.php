<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\MoneyController;
use App\Http\Controllers\AgentsController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\WelcomeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Default route
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

// ====================================
// USER APP ROUTES
// ====================================

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'createAccount'])->name('auth.signup');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    // Deriv OAuth Routes
    Route::get('/deriv-oauth', [AuthController::class, 'derivOAuth'])->name('auth.deriv.oauth');
    Route::get('/deriv-callback', [AuthController::class, 'derivCallback'])->name('auth.deriv.callback');
    Route::get('/deriv-session-data', [AuthController::class, 'getDerivSessionData'])->name('auth.deriv.session');

    // OTP Routes
    Route::post('/send-otp', [AuthController::class, 'sendOtp'])->name('auth.send.otp');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('auth.verify.otp');
    Route::post('/update-password', [AuthController::class, 'updatePassword'])->name('auth.update.password');
});

// Protected User Routes (require authentication)
Route::middleware(['auth'])->group(function () {

    // User Profile & Account Management
    Route::post('/password-update', [MainController::class, 'passwordUpdate'])->name('user.password.update');
    Route::post('/update-phone', [MainController::class, 'updatePhone'])->name('user.phone.update');

    // Deriv Operations
    Route::post('/deriv-withdraw', [MainController::class, 'withdrawFromDeriv'])->name('deriv.withdraw');
    Route::post('/deriv-deposit', [MainController::class, 'depositToDeriv'])->name('deriv.deposit');

    // Mpesa Operations
    Route::post('/mpesa-deposit', [MainController::class, 'depositFromMpesa'])->name('mpesa.deposit');
    Route::post('/mpesa-withdraw', [MainController::class, 'withdrawToMpesa'])->name('mpesa.withdraw');

    // User Data & Transactions
    Route::get('/home-data', [MainController::class, 'home'])->name('user.home');
    Route::get('/user-transactions', [MainController::class, 'transactions'])->name('user.transactions');
    Route::get('/balance', [MainController::class, 'balance'])->name('user.balance');
    Route::get('/outbox', [MainController::class, 'outbox'])->name('user.outbox');

    // P2P & Payments
    Route::post('/send-p2p', [MainController::class, 'stepakashP2P'])->name('user.p2p.send');
    Route::post('/pay-now', [MainController::class, 'payNow'])->name('user.pay.now');
    Route::post('/send-gift', [MainController::class, 'sendGift'])->name('user.send.gift');
    Route::get('/query-receipt', [MainController::class, 'queryReceipt'])->name('user.query.receipt');

    // Testing route
    Route::get('/mpesa-b2c-test', [MainController::class, 'mpesaB2cTest'])->name('mpesa.b2c.test');
});

// Money/Payment Callbacks (usually public for external services)
Route::prefix('callbacks')->group(function () {
    Route::post('/stk-results', [MoneyController::class, 'stkResults'])->name('callbacks.stk.results');
    Route::post('/b2c-result', [MoneyController::class, 'b2cResult'])->name('callbacks.b2c.result');
    Route::post('/register-url', [MoneyController::class, 'registerUrl'])->name('callbacks.register.url');
    Route::post('/mpesa-c2b-results', [MoneyController::class, 'mpesaC2bResults'])->name('callbacks.mpesa.c2b.results');
    Route::post('/validation-url', [MoneyController::class, 'validationUrl'])->name('callbacks.validation.url');
    Route::post('/next-receipt', [MoneyController::class, 'nextReceipt'])->name('callbacks.next.receipt');
});

// ====================================
// ADMIN ROUTES WITH AUTHENTICATION
// ====================================

Route::prefix('admin')->group(function () {
    // Admin Authentication
    Route::post('/login', [AuthController::class, 'adminLogin'])->name('admin.login');

    // Protected Admin Routes
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/dashboard', [MainController::class, 'adminHome'])->name('admin.dashboard');

        // Requests Management
        Route::get('/deposits-request', [MainController::class, 'depositsRequest'])->name('admin.deposits.request');
        Route::get('/withdrawal-request', [MainController::class, 'withdrawalRequest'])->name('admin.withdrawal.request');
        Route::post('/process-deposit', [MainController::class, 'processDepoRequest'])->name('admin.process.deposit');
        Route::post('/process-withdrawal', [MainController::class, 'processWithdrawalRequest'])->name('admin.process.withdrawal');
        Route::post('/reject-withdrawal', [MainController::class, 'rejectWithdrawalRequest'])->name('admin.reject.withdrawal');

        // User Management
        Route::get('/app-users', [MainController::class, 'adminAppUsers'])->name('admin.app.users');
        Route::get('/system-users', [MainController::class, 'adminSystemUsers'])->name('admin.system.users');
        Route::get('/active-users', [MainController::class, 'activeUsers'])->name('admin.active.users');
        Route::get('/user-account/{id}', [MainController::class, 'getUserAccount'])->name('admin.user.account');
        Route::post('/update-user-account', [MainController::class, 'updateUserAccount'])->name('admin.update.user.account');
        Route::post('/create-admin', [MainController::class, 'adminCreateAccount'])->name('admin.create.admin');

        // Wallet Management
        Route::post('/deduct-from-wallet', [MainController::class, 'deductFromWallet'])->name('admin.deduct.wallet');
        Route::post('/add-user-wallet', [MainController::class, 'addUserWallet'])->name('admin.add.wallet');

        // Exchange Rates
        Route::get('/view-rate', [MainController::class, 'viewRate'])->name('admin.view.rate');
        Route::post('/set-exchange', [MainController::class, 'setExchange'])->name('admin.set.exchange');
        Route::get('/get-rates', [MainController::class, 'getRates'])->name('admin.get.rates');

        // Mpesa Transactions
        Route::get('/mpesa-deposits', [MainController::class, 'mpesaDeposits'])->name('admin.mpesa.deposits');
        Route::get('/mpesa-withdrawals', [MainController::class, 'mpesaWithdrawals'])->name('admin.mpesa.withdrawals');
        Route::get('/mpesa-withdrawals-transactions', [MainController::class, 'mpesaWithdrawalsTransactions'])->name('admin.mpesa.withdrawals.transactions');

        // Crypto Operations
        Route::get('/crypto-deposit-request', [MainController::class, 'cryptoDepositRequest'])->name('admin.crypto.deposit.request');
        Route::get('/crypto-withdrawal-request', [MainController::class, 'cryptoWithdrawalRequest'])->name('admin.crypto.withdrawal.request');
        Route::get('/crypto-request', [MainController::class, 'cryptoRequest'])->name('admin.crypto.request');
        Route::post('/process-crypto-deposit', [MainController::class, 'processCryptoDeposit'])->name('admin.process.crypto.deposit');
        Route::post('/process-crypto-withdraw', [MainController::class, 'processCryptoWithdraw'])->name('admin.process.crypto.withdraw');
        Route::post('/reject-crypto-withdraw', [MainController::class, 'rejectCryptoWithdraw'])->name('admin.reject.crypto.withdraw');

        // Reports
        Route::get('/stepakash-debit-report', [MainController::class, 'stepakashDebitReport'])->name('admin.stepakash.debit.report');
        Route::get('/stepakash-credit-report', [MainController::class, 'stepakashCreditReport'])->name('admin.stepakash.credit.report');
        Route::get('/app-audit', [MainController::class, 'appAudit'])->name('admin.app.audit');

        // Gift Requests
        Route::get('/gift-request', [MainController::class, 'giftRequest'])->name('admin.gift.request');

        // Transaction Review
        Route::post('/review-transaction', [MainController::class, 'reviewTransaction'])->name('admin.review.transaction');
    });
});

// ====================================
// AGENTS ROUTES
// ====================================

Route::prefix('agents')->group(function () {
    Route::post('/auth', [AgentsController::class, 'agentsAuth'])->name('agents.auth');

    Route::middleware(['auth', 'agent'])->group(function () {
        Route::post('/set-commission', [AgentsController::class, 'setAgentCommission'])->name('agents.set.commission');
        Route::get('/view-service-commission', [AgentsController::class, 'viewServiceCommission'])->name('agents.view.service.commission');
        Route::post('/withdraw-to-agent', [AgentsController::class, 'withdrawToAgent'])->name('agents.withdraw.to.agent');
    });
});

// ====================================
// BUSINESS MODULE ROUTES
// ====================================

Route::prefix('business')->group(function () {
    Route::post('/create-merchant', [BusinessController::class, 'createMerchant'])->name('business.create.merchant');
    Route::get('/view-merchant', [BusinessController::class, 'viewMerchant'])->name('business.view.merchant');
});

// ====================================
// PARTNER ROUTES
// ====================================

Route::prefix('partner')->group(function () {
    // Partner Authentication
    Route::post('/generate-token', [PartnerController::class, 'generateToken'])->name('partner.generate.token');
    Route::post('/auth', [PartnerController::class, 'partnerAuth'])->name('partner.auth');
    Route::post('/user-auth', [PartnerController::class, 'userAuth'])->name('partner.user.auth');
    Route::post('/logout', [PartnerController::class, 'logout'])->name('partner.logout');

    // Protected Partner Routes
    Route::middleware(['auth', 'partner'])->group(function () {
        // Dashboard & Reports
        Route::get('/dashboard-data', [PartnerController::class, 'dashboardData'])->name('partner.dashboard.data');
        Route::get('/custom-reports', [PartnerController::class, 'customReports'])->name('partner.custom.reports');
        Route::get('/account-balance', [PartnerController::class, 'accountBalance'])->name('partner.account.balance');
        Route::get('/float-report', [PartnerController::class, 'floatReport'])->name('partner.float.report');
        Route::get('/audit-report', [PartnerController::class, 'auditReport'])->name('partner.audit.report');
        Route::get('/transaction-report', [PartnerController::class, 'transactionReport'])->name('partner.transaction.report');

        // User Management
        Route::post('/create-user', [PartnerController::class, 'createUser'])->name('partner.create.user');
        Route::get('/view-users', [PartnerController::class, 'viewUsers'])->name('partner.view.users');

        // Account Operations
        Route::post('/top-up-account', [PartnerController::class, 'topUpAccount'])->name('partner.top.up.account');
        Route::post('/move-funds', [PartnerController::class, 'moveFunds'])->name('partner.move.funds');
        Route::post('/transfer-funds', [PartnerController::class, 'transferFunds'])->name('partner.transfer.funds');
        Route::get('/get-partner-account', [PartnerController::class, 'getPartnerAccount'])->name('partner.get.partner.account');

        // Transaction Management
        Route::get('/pending-transfers', [PartnerController::class, 'pendingTransfers'])->name('partner.pending.transfers');
        Route::get('/declined-transaction', [PartnerController::class, 'declinedTransaction'])->name('partner.declined.transaction');
        Route::post('/approve-transfer', [PartnerController::class, 'approveTransfer'])->name('partner.approve.transfer');
        Route::post('/decline-transfer', [PartnerController::class, 'declineTransfer'])->name('partner.decline.transfer');
        Route::post('/initiate-transaction', [PartnerController::class, 'initiateTransaction'])->name('partner.initiate.transaction');
        Route::post('/reverse-transaction', [PartnerController::class, 'reverseTransaction'])->name('partner.reverse.transaction');
        Route::post('/verify-transaction', [PartnerController::class, 'verifyTransaction'])->name('partner.verify.transaction');

        // Testing
        Route::get('/test', [PartnerController::class, 'test'])->name('partner.test');
    });
});
