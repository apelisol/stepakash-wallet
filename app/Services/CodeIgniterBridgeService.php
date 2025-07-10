<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeIgniterBridgeService
{
    protected $ciBaseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->ciBaseUrl = env('CI_BASE_URL', 'https://stepakash.com');
        $this->apiKey = env('CI_API_KEY', 'apelisoltech2025');
    }

    /**
     * Validate session with CodeIgniter system
     */
    public function validateSession($session_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/validate_session', [
                'session_id' => $session_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['valid'])) {
                return $data;
            }

            Log::error('Session validation failed', ['response' => $data]);
            return [
                'valid' => false,
                'message' => $data['message'] ?? 'Session validation failed',
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Session validation error: ' . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Session validation service unavailable',
                'data' => null
            ];
        }
    }

    /**
     * Get user data including balance and rates
     */
    public function getUserData($wallet_id, $session_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/user_data', [
                'wallet_id' => $wallet_id,
                'session_id' => $session_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success']) {
                return $data;
            }

            Log::error('Failed to get user data', ['response' => $data]);
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to get user data',
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Get user data error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'User data service unavailable',
                'data' => null
            ];
        }
    }

    /**
     * Get sell rate from CodeIgniter
     */
    public function getSellRate()
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->get($this->ciBaseUrl . '/api/sell_rate');

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success']) {
                return $data['data'];
            }

            Log::error('Failed to get sell rate', ['response' => $data]);
            return [];
        } catch (\Exception $e) {
            Log::error('Get sell rate error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check for duplicate transactions
     */
    public function checkDuplicateTransaction($transaction_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/check_transaction', [
                'transaction_id' => $transaction_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['is_duplicate'])) {
                return $data['is_duplicate'];
            }

            Log::error('Duplicate check failed', ['response' => $data]);
            return false;
        } catch (\Exception $e) {
            Log::error('Duplicate check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check for pending withdrawals
     */
    public function checkPendingWithdrawals($wallet_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/pending_withdrawals', [
                'wallet_id' => $wallet_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['pending_withdrawals'])) {
                return $data['pending_withdrawals'];
            }

            Log::error('Pending withdrawals check failed', ['response' => $data]);
            return [];
        } catch (\Exception $e) {
            Log::error('Pending withdrawals check error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create deposit request in CodeIgniter DB
     */
    public function createDepositRequest($data)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/create_deposit_request', $data);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
                return $responseData;
            }

            Log::error('Create deposit request failed', ['response' => $responseData]);
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Failed to create deposit request'
            ];
        } catch (\Exception $e) {
            Log::error('Create deposit request error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Deposit request service unavailable'
            ];
        }
    }

    /**
     * Update deposit request in CodeIgniter DB
     */
    public function updateDepositRequest($transaction_id, $data)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/update_deposit_request', [
                'transaction_id' => $transaction_id,
                'data' => $data
            ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
                return $responseData;
            }

            Log::error('Update deposit request failed', ['response' => $responseData]);
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Failed to update deposit request'
            ];
        } catch (\Exception $e) {
            Log::error('Update deposit request error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Deposit update service unavailable'
            ];
        }
    }

    /**
     * Get deposit request from CodeIgniter DB
     */
    public function getDepositRequest($request_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/get_deposit_request', [
                'request_id' => $request_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success']) {
                return $data['data'];
            }

            Log::error('Get deposit request failed', ['response' => $data]);
            return [];
        } catch (\Exception $e) {
            Log::error('Get deposit request error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create withdrawal request in CodeIgniter DB
     */
    public function createWithdrawalRequest($data)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/create_withdrawal_request', $data);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
                return $responseData;
            }

            Log::error('Create withdrawal request failed', ['response' => $responseData]);
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Failed to create withdrawal request'
            ];
        } catch (\Exception $e) {
            Log::error('Create withdrawal request error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Withdrawal request service unavailable'
            ];
        }
    }

    /**
     * Update withdrawal request in CodeIgniter DB
     */
    public function updateWithdrawalRequest($request_id, $data)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/update_withdrawal_request', [
                'request_id' => $request_id,
                'data' => $data
            ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
                return $responseData;
            }

            Log::error('Update withdrawal request failed', ['response' => $responseData]);
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Failed to update withdrawal request'
            ];
        } catch (\Exception $e) {
            Log::error('Update withdrawal request error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Withdrawal update service unavailable'
            ];
        }
    }

    /**
     * Get withdrawal request from CodeIgniter DB
     */
    public function getWithdrawalRequest($request_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/get_withdrawal_request', [
                'request_id' => $request_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success']) {
                return $data['data'];
            }

            Log::error('Get withdrawal request failed', ['response' => $data]);
            return [];
        } catch (\Exception $e) {
            Log::error('Get withdrawal request error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create ledger entries in CodeIgniter DB
     */
    public function createLedgerEntries($data)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/create_ledger_entries', $data);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
                return $responseData;
            }

            Log::error('Create ledger entries failed', ['response' => $responseData]);
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Failed to create ledger entries'
            ];
        } catch (\Exception $e) {
            Log::error('Create ledger entries error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ledger service unavailable'
            ];
        }
    }

    /**
     * Get user info from CodeIgniter DB
     */
    public function getUserInfo($wallet_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/get_user_info', [
                'wallet_id' => $wallet_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success']) {
                return $data['data'];
            }

            Log::error('Get user info failed', ['response' => $data]);
            return [];
        } catch (\Exception $e) {
            Log::error('Get user info error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Send SMS via CodeIgniter system
     */
    public function sendSMS($phone, $message)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/send_sms', [
                'phone' => $phone,
                'message' => $message
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success']) {
                return true;
            }

            Log::error('Send SMS failed', ['response' => $data]);
            return false;
        } catch (\Exception $e) {
            Log::error('Send SMS error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get transactions for a wallet
     */
    public function getTransactions($wallet_id)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey
            ])->post($this->ciBaseUrl . '/api/get_transactions', [
                'wallet_id' => $wallet_id
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success']) {
                return $data['data'];
            }

            Log::error('Get transactions failed', ['response' => $data]);
            return [];
        } catch (\Exception $e) {
            Log::error('Get transactions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate a transaction ID
     */
    public function generateTransactionId()
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }

    /**
     * Generate a transaction number
     */
    public function generateTransactionNumber()
    {
        return 'TXN' . date('YmdHis') . rand(1000, 9999);
    }
}
