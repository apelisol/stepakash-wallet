<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DerivWebSocketService;
use App\Jobs\ProcessDerivDeposit;
use App\Models\DerivDepositRequest;

class DerivDepositController extends Controller
{
    private $derivService;
    private $codeigniterDb;

    public function __construct(DerivWebSocketService $derivService)
    {
        $this->derivService = $derivService;

        // Secondary database connection to CodeIgniter DB
        $this->codeigniterDb = DB::connection('codeigniter');
    }

    /**
     * Handle deposit request from CodeIgniter
     */
    public function initiateDeposit(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'cr_number' => 'required|string|min:8|max:12',
            'amount' => 'required|numeric|min:2.5',
            'transaction_id' => 'required|string',
            'wallet_id' => 'required|string'
        ]);

        try {
            // 1. Validate session with CodeIgniter (single API call)
            $sessionValidation = $this->validateCodeIgniterSession($validated['session_id']);

            if (!$sessionValidation['valid']) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Invalid session',
                    'data' => null
                ], 401);
            }

            // 2. Check for duplicate transactions
            $existingDeposit = DerivDepositRequest::where('transaction_id', $validated['transaction_id'])
                ->where('status', 1)
                ->first();

            if ($existingDeposit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction already processed',
                    'data' => null
                ], 400);
            }

            // 3. Get user details and validate balance
            $userDetails = $this->getUserDetails($validated['wallet_id']);
            $amountUSD = $validated['amount'];

            if ($userDetails['balance_usd'] < $amountUSD) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient funds. Your balance is $' . number_format($userDetails['balance_usd'], 2) . ' USD.',
                    'data' => null
                ], 400);
            }

            // 4. Queue the deposit for async processing
            $depositData = array_merge($validated, [
                'amount_usd' => $amountUSD,
                'conversion_rate' => $userDetails['conversion_rate'],
                'user_phone' => $userDetails['phone'],
                'status' => 'pending'
            ]);

            // Save deposit request
            $depositRequest = DerivDepositRequest::create($depositData);

            // Dispatch async job
            ProcessDerivDeposit::dispatch($depositRequest->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Deposit request submitted. You will receive confirmation shortly.',
                'data' => [
                    'deposit_id' => $depositRequest->id,
                    'transaction_id' => $validated['transaction_id'],
                    'amount_usd' => $amountUSD,
                    'estimated_completion' => now()->addMinutes(2)->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Deposit initiation failed', [
                'error' => $e->getMessage(),
                'request_data' => $validated
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Deposit initiation failed. Please try again.',
                'data' => null
            ], 500);
        }
    }

    /**
     * Check deposit status
     */
    public function checkDepositStatus(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'session_id' => 'required|string'
        ]);

        // Quick session validation
        $sessionValidation = $this->validateCodeIgniterSession($validated['session_id']);
        if (!$sessionValidation['valid']) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Invalid session',
                'data' => null
            ], 401);
        }

        $deposit = DerivDepositRequest::where('transaction_id', $validated['transaction_id'])->first();

        if (!$deposit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deposit not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Deposit status retrieved',
            'data' => [
                'transaction_id' => $deposit->transaction_id,
                'status' => $deposit->status,
                'amount_usd' => $deposit->amount_usd,
                'cr_number' => $deposit->cr_number,
                'created_at' => $deposit->created_at,
                'completed_at' => $deposit->completed_at,
                'error_message' => $deposit->error_message
            ]
        ]);
    }

    /**
     * Validate CodeIgniter session via API
     */
    private function validateCodeIgniterSession($sessionId)
    {
        try {
            $response = Http::timeout(10)->post(config('services.codeigniter.base_url') . '/checkDerivSession', [
                'session_id' => $sessionId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'valid' => $data['status'] === 'success',
                    'data' => $data['data'] ?? null
                ];
            }

            return ['valid' => false, 'data' => null];
        } catch (\Exception $e) {
            Log::error('Session validation failed', ['error' => $e->getMessage()]);
            return ['valid' => false, 'data' => null];
        }
    }

    /**
     * Get user details from CodeIgniter database
     */
    private function getUserDetails($walletId)
    {
        $summary = $this->codeigniterDb->table('customer_transection_summary')
            ->where('wallet_id', $walletId)
            ->first();

        $buyRate = $this->codeigniterDb->table('exchange')
            ->where('exchange_type', 1)
            ->where('service_type', 1)
            ->first();

        $user = $this->codeigniterDb->table('customers')
            ->where('wallet_id', $walletId)
            ->first();

        $totalCredit = (float) str_replace(',', '', $summary->total_credit ?? 0);
        $totalDebit = (float) str_replace(',', '', $summary->total_debit ?? 0);
        $balanceKes = $totalCredit - $totalDebit;
        $conversionRate = $buyRate->kes ?? 1;

        return [
            'balance_kes' => $balanceKes,
            'balance_usd' => $balanceKes / $conversionRate,
            'conversion_rate' => $conversionRate,
            'phone' => $user->phone ?? '',
            'bought_at' => $buyRate->bought_at ?? 0
        ];
    }
}
