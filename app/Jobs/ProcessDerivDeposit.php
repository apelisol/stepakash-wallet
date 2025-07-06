<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\DerivDepositRequest;
use App\Services\DerivWebSocketService;
use App\Services\SmsService;

class ProcessDerivDeposit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $depositId;
    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct($depositId)
    {
        $this->depositId = $depositId;
    }

    public function handle(DerivWebSocketService $derivService, SmsService $smsService)
    {
        $deposit = DerivDepositRequest::find($this->depositId);

        if (!$deposit) {
            Log::error('Deposit not found', ['deposit_id' => $this->depositId]);
            return;
        }

        try {
            $deposit->update(['status' => 'processing']);

            // Attempt Deriv transfer
            $transferResult = $derivService->transferToAccount(
                $deposit->cr_number,
                $deposit->amount_usd
            );

            if ($transferResult['success']) {
                // Transfer successful
                DB::transaction(function () use ($deposit, $transferResult) {
                    // Update Laravel record
                    $deposit->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'deriv_response' => json_encode($transferResult['data'])
                    ]);

                    // Update CodeIgniter database
                    $this->updateCodeIgniterDatabase($deposit);
                });

                // Send SMS notifications
                $smsService->sendDerivDepositSuccess($deposit);

                Log::info('Deposit completed successfully', [
                    'deposit_id' => $deposit->id,
                    'transaction_id' => $deposit->transaction_id
                ]);
            } else {
                // Transfer failed
                $deposit->update([
                    'status' => 'failed',
                    'error_message' => $transferResult['message']
                ]);

                Log::error('Deposit transfer failed', [
                    'deposit_id' => $deposit->id,
                    'error' => $transferResult['message']
                ]);
            }
        } catch (\Exception $e) {
            $deposit->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            Log::error('Deposit processing failed', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    private function updateCodeIgniterDatabase($deposit)
    {
        $codeigniterDb = DB::connection('codeigniter');

        // Update deriv_deposit_request table
        $codeigniterDb->table('deriv_deposit_request')->insert([
            'transaction_id' => $deposit->transaction_id,
            'wallet_id' => $deposit->wallet_id,
            'cr_number' => $deposit->cr_number,
            'amount' => $deposit->amount_usd,
            'status' => 1,
            'deposited' => $deposit->amount_usd,
            'processed_at' => now(),
            'created_at' => now()
        ]);

        // Add customer ledger entry
        $codeigniterDb->table('customer_ledger')->insert([
            'transaction_id' => $deposit->transaction_id,
            'wallet_id' => $deposit->wallet_id,
            'description' => 'Deposit to Deriv',
            'amount' => $deposit->amount_usd,
            'currency' => 'USD',
            'cr_dr' => 'dr',
            'deriv' => 1,
            'status' => 1,
            'created_at' => now()
        ]);
    }
}
