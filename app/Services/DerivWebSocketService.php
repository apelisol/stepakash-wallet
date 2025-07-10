<?php

namespace App\Services;

use App\Services\CodeIgniterBridgeService;
use WebSocket\Client as WebSocketClient;
use WebSocket\ConnectionException;
use Illuminate\Support\Facades\Log;

class DerivService
{
    protected $bridgeService;
    protected $appId = 76420;
    protected $derivToken = 'DidPRclTKE0WYtT';
    protected $derivEndpoint = 'ws.derivws.com';

    public function __construct(CodeIgniterBridgeService $bridgeService)
    {
        $this->bridgeService = $bridgeService;
    }

    /**
     * Process deposit to Deriv account
     */
    public function processDeposit($transaction_id, $wallet_id, $crNumber, $amount, $session_id)
    {
        // Get user balance and rates from CodeIgniter
        $userData = $this->bridgeService->getUserData($wallet_id, $session_id);

        if (!$userData['success']) {
            throw new \Exception($userData['message']);
        }

        $summary = $userData['data']['summary'];
        $buyRate = $userData['data']['buy_rate'];

        // Calculate amounts
        $total_credit = (float) str_replace(',', '', $summary['total_credit']);
        $total_debit = (float) str_replace(',', '', $summary['total_debit']);
        $total_balance_kes = $total_credit - $total_debit;
        $conversionRate = $buyRate['kes'];
        $amountUSD = round($amount / $conversionRate, 2);

        // Validate minimum amount
        if ($amountUSD < 2.5) {
            throw new \Exception('The minimum deposit amount is $2.50 USD.');
        }

        // Validate sufficient balance
        $total_balance_usd = $total_balance_kes / $conversionRate;
        if ($total_balance_usd < $amountUSD) {
            throw new \Exception('Insufficient funds. Your balance is $' . number_format($total_balance_usd, 2) . ' USD.');
        }

        // Check for duplicate transactions
        $duplicateCheck = $this->bridgeService->checkDuplicateTransaction($transaction_id);
        if ($duplicateCheck) {
            throw new \Exception('Transaction already processed.');
        }

        // Create deposit request in CodeIgniter DB
        $transactionNumber = $this->bridgeService->generateTransactionNumber();
        $depositData = [
            'transaction_id' => $transaction_id,
            'transaction_number' => $transactionNumber,
            'wallet_id' => $wallet_id,
            'cr_number' => $crNumber,
            'amount' => $amountUSD,
            'rate' => $conversionRate,
            'status' => 0, // Pending
            'request_date' => now()->toDateTimeString()
        ];

        $createRequest = $this->bridgeService->createDepositRequest($depositData);
        if (!$createRequest['success']) {
            throw new \Exception('Failed to create deposit request: ' . $createRequest['message']);
        }

        // Attempt Deriv transfer
        $transferResult = $this->transferToDerivAccount($crNumber, $amountUSD);

        if (!$transferResult['success']) {
            throw new \Exception($transferResult['message']);
        }

        // Update deposit request as successful
        $updateData = [
            'status' => 1,
            'deposited' => $amountUSD,
            'processed_at' => now()->toDateTimeString()
        ];

        $updateRequest = $this->bridgeService->updateDepositRequest($transaction_id, $updateData);
        if (!$updateRequest['success']) {
            // Critical error - transfer succeeded but DB update failed
            Log::critical('Deriv transfer succeeded but DB update failed', [
                'transaction_id' => $transaction_id,
                'transfer_data' => $transferResult
            ]);

            throw new \Exception('Transfer completed but system update failed. Contact support with transaction ID: ' . $transaction_id);
        }

        // Create ledger entries
        $boughtbuy = $buyRate['bought_at'];
        $mycharge = ($buyRate['kes'] - $boughtbuy);
        $newcharge = (float)$mycharge * $amountUSD;

        $ledgerData = [
            'transaction_id' => $transaction_id,
            'transaction_number' => $transactionNumber,
            'wallet_id' => $wallet_id,
            'amount' => $amount,
            'amount_usd' => $amountUSD,
            'rate' => $conversionRate,
            'charge' => $newcharge,
            'description' => 'Deposit to Deriv',
            'cr_dr' => 'dr'
        ];

        $createLedger = $this->bridgeService->createLedgerEntries($ledgerData);
        if (!$createLedger['success']) {
            Log::error('Ledger creation failed after successful transfer', [
                'transaction_id' => $transaction_id,
                'ledger_data' => $ledgerData
            ]);
        }

        // Get user phone for notification
        $userInfo = $this->bridgeService->getUserInfo($wallet_id);
        $phone = $userInfo['phone'] ?? null;

        if ($phone) {
            $message = 'Txn ID: ' . $transactionNumber . ', deposit of $' . $amountUSD . ' USD successfully completed to Deriv account ' . $crNumber;
            $this->bridgeService->sendSMS($phone, $message);
            $this->bridgeService->sendSMS('0703416091', "Deposit completed: $" . $amountUSD . " USD to " . $crNumber);
        }

        return [
            'status' => 'success',
            'message' => 'Deposit processed successfully',
            'data' => $transferResult['data']
        ];
    }

    /**
     * Process withdrawal from Deriv account
     */
    public function processWithdrawal($wallet_id, $crNumber, $amount, $session_id)
    {
        // Validate minimum withdrawal amount
        if ($amount < 2.5) {
            throw new \Exception('The minimum withdrawal amount is $2.50 USD.');
        }

        // Get sell rate from CodeIgniter
        $sellRate = $this->bridgeService->getSellRate();
        if (empty($sellRate)) {
            throw new \Exception('Exchange rate not available. Please try again later.');
        }

        // Check for pending withdrawals
        $pendingWithdrawals = $this->bridgeService->checkPendingWithdrawals($wallet_id);
        if (!empty($pendingWithdrawals)) {
            throw new \Exception('You have a pending withdrawal request. Please wait for it to be processed.');
        }

        // Create withdrawal request
        $withdrawalData = [
            'wallet_id' => $wallet_id,
            'cr_number' => $crNumber,
            'amount' => $amount,
            'rate' => $sellRate['kes'],
            'status' => 0, // Pending
            'request_date' => now()->toDateTimeString()
        ];

        $createRequest = $this->bridgeService->createWithdrawalRequest($withdrawalData);
        if (!$createRequest['success']) {
            throw new \Exception('Failed to create withdrawal request: ' . $createRequest['message']);
        }

        // Get user info for notification
        $userInfo = $this->bridgeService->getUserInfo($wallet_id);
        $phone = $userInfo['phone'] ?? null;

        if ($phone) {
            $message = 'Withdrawal request for $' . number_format($amount, 2) . ' USD is being processed. You will receive confirmation shortly.';
            $this->bridgeService->sendSMS($phone, $message);
            $this->bridgeService->sendSMS('0703416091', "New withdrawal request: $" . $amount . " USD from " . $crNumber);
        }

        return [
            'status' => 'success',
            'message' => 'Withdrawal request created successfully',
            'data' => [
                'request_id' => $createRequest['request_id'],
                'amount' => $amount,
                'cr_number' => $crNumber
            ]
        ];
    }

    /**
     * Complete a deposit process (callback from Deriv)
     */
    public function completeDeposit($request_id)
    {
        // Get deposit request from CodeIgniter DB
        $depositRequest = $this->bridgeService->getDepositRequest($request_id);
        if (empty($depositRequest)) {
            throw new \Exception('Deposit request not found');
        }

        if ($depositRequest['status'] == 1) {
            return [
                'status' => 'success',
                'message' => 'Deposit already processed',
                'data' => $depositRequest
            ];
        }

        $amount = $depositRequest['amount'];
        $crNumber = $depositRequest['cr_number'];
        $wallet_id = $depositRequest['wallet_id'];

        // Attempt Deriv transfer
        $transferResult = $this->transferToDerivAccount($crNumber, $amount);

        if (!$transferResult['success']) {
            throw new \Exception($transferResult['message']);
        }

        // Update deposit request
        $updateData = [
            'status' => 1,
            'deposited' => $amount,
            'processed_at' => now()->toDateTimeString()
        ];

        $updateRequest = $this->bridgeService->updateDepositRequest($request_id, $updateData);
        if (!$updateRequest['success']) {
            throw new \Exception('Failed to update deposit request: ' . $updateRequest['message']);
        }

        // Get user info for notification
        $userInfo = $this->bridgeService->getUserInfo($wallet_id);
        $phone = $userInfo['phone'] ?? null;

        if ($phone) {
            $message = $depositRequest['transaction_number'] . ' processed, ' . $amount . 'USD has been successfully deposited to your deriv account ' . $crNumber;
            $this->bridgeService->sendSMS($phone, $message);
        }

        return [
            'status' => 'success',
            'message' => 'Deposit processed successfully',
            'data' => $transferResult['data']
        ];
    }

    /**
     * Complete a withdrawal process
     */
    public function completeWithdrawal($request_id)
    {
        // Get withdrawal request from CodeIgniter DB
        $withdrawalRequest = $this->bridgeService->getWithdrawalRequest($request_id);
        if (empty($withdrawalRequest)) {
            throw new \Exception('Withdrawal request not found');
        }

        if ($withdrawalRequest['status'] == 1) {
            return [
                'status' => 'success',
                'message' => 'Withdrawal already processed',
                'data' => $withdrawalRequest
            ];
        }

        $amount = $withdrawalRequest['amount'];
        $wallet_id = $withdrawalRequest['wallet_id'];
        $crNumber = $withdrawalRequest['cr_number'];
        $rate = $withdrawalRequest['rate'];

        // Get sell rate details
        $sellRate = $this->bridgeService->getSellRate();
        $boughtsell = $sellRate['bought_at'];
        $mycharge = ($boughtsell - $sellRate['kes']);
        $newcharge = (float)$mycharge * $amount;

        // Update withdrawal request
        $updateData = [
            'status' => 1,
            'withdraw' => $amount,
            'processed_at' => now()->toDateTimeString()
        ];

        $updateRequest = $this->bridgeService->updateWithdrawalRequest($request_id, $updateData);
        if (!$updateRequest['success']) {
            throw new \Exception('Failed to update withdrawal request: ' . $updateRequest['message']);
        }

        // Create ledger entries
        $amountKESAfterCharge = ((float) $amount * (float) $rate);
        $totalAmt = $amountKESAfterCharge;

        $ledgerData = [
            'transaction_id' => $withdrawalRequest['transaction_id'] ?? $this->bridgeService->generateTransactionId(),
            'transaction_number' => $withdrawalRequest['transaction_number'] ?? $this->bridgeService->generateTransactionNumber(),
            'wallet_id' => $wallet_id,
            'amount' => $amount,
            'amount_kes' => $amountKESAfterCharge,
            'rate' => $rate,
            'charge' => $newcharge,
            'description' => 'Withdrawal from Deriv',
            'cr_dr' => 'cr'
        ];

        $createLedger = $this->bridgeService->createLedgerEntries($ledgerData);
        if (!$createLedger['success']) {
            Log::error('Ledger creation failed for withdrawal', [
                'request_id' => $request_id,
                'ledger_data' => $ledgerData
            ]);
        }

        // Get user info for notification
        $userInfo = $this->bridgeService->getUserInfo($wallet_id);
        $phone = $userInfo['phone'] ?? null;

        if ($phone) {
            $message = $withdrawalRequest['transaction_number'] . ', ' . $amount . 'USD has been successfully withdrawn from your deriv account ' . $crNumber;
            $this->bridgeService->sendSMS($phone, $message);
        }

        return [
            'status' => 'success',
            'message' => 'Withdrawal processed successfully',
            'data' => $withdrawalRequest
        ];
    }

    /**
     * Transfer funds to Deriv account via WebSocket
     */
    private function transferToDerivAccount($loginid, $amount)
    {
        try {
            $url = "wss://{$this->derivEndpoint}/websockets/v3?app_id={$this->appId}";

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $client = new WebSocketClient($url, [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ], ['context' => $context]);

            // 1. Authorize with Payment Agent token
            $client->send(json_encode(["authorize" => $this->derivToken]));
            $authResponse = $client->receive();
            $authData = json_decode($authResponse, true);

            if (isset($authData['error'])) {
                Log::error('Deriv auth failed: ' . ($authData['error']['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'message' => 'Authorization failed: ' . ($authData['error']['message'] ?? 'Unknown error'),
                    'data' => $authData
                ];
            }

            // 2. Validate payment agent balance
            if (isset($authData['authorize']['balance']) && $authData['authorize']['balance'] < $amount) {
                Log::error('Insufficient payment agent balance');
                return [
                    'success' => false,
                    'message' => 'Insufficient payment agent balance',
                    'data' => $authData
                ];
            }

            // 3. Make the transfer
            $transferRequest = [
                "paymentagent_transfer" => 1,
                "transfer_to" => $loginid,
                "amount" => $amount,
                "currency" => "USD",
                "description" => "Deposit via Stepakash"
            ];

            $client->send(json_encode($transferRequest));
            $transferResponse = $client->receive();
            $transferData = json_decode($transferResponse, true);

            $client->close();

            if (isset($transferData['error'])) {
                Log::error('Deriv transfer failed: ' . ($transferData['error']['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'message' => $transferData['error']['message'] ?? 'Transfer failed',
                    'data' => $transferData
                ];
            }

            if (isset($transferData['paymentagent_transfer']) && $transferData['paymentagent_transfer'] == 1) {
                Log::info("Transfer successful to $loginid: $amount USD");
                return [
                    'success' => true,
                    'message' => 'Transfer successful',
                    'data' => $transferData
                ];
            }

            Log::error('Unexpected response from Deriv API', ['response' => $transferData]);
            return [
                'success' => false,
                'message' => 'Unexpected response from Deriv API',
                'data' => $transferData
            ];
        } catch (ConnectionException $e) {
            Log::error('Deriv WebSocket connection error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Deriv transfer error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Transfer error: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
