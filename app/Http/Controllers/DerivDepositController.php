<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DerivService;
use App\Services\CodeIgniterBridgeService;
use Illuminate\Support\Facades\Validator;
use WebSocket\Client as WebSocketClient;
use WebSocket\ConnectionException;

class DerivController extends Controller
{
    protected $derivService;
    protected $bridgeService;

    public function __construct(DerivService $derivService, CodeIgniterBridgeService $bridgeService)
    {
        $this->derivService = $derivService;
        $this->bridgeService = $bridgeService;
    }

    /**
     * Handle deposit to Deriv account
     */
    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required',
            'crNumber' => 'required|min:8|max:12',
            'amount' => 'required|numeric|min:2.5',
            'transaction_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first(),
                'data' => null
            ], 400);
        }

        // Validate session with CodeIgniter system
        $sessionValidation = $this->bridgeService->validateSession($request->session_id);

        if (!$sessionValidation['valid']) {
            return response()->json([
                'status' => 'fail',
                'message' => $sessionValidation['message'],
                'data' => null
            ], 401);
        }

        try {
            $result = $this->derivService->processDeposit(
                $request->transaction_id,
                $sessionValidation['data']['wallet_id'],
                $request->crNumber,
                $request->amount,
                $request->session_id
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Processing error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Handle withdrawal from Deriv account
     */
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required',
            'crNumber' => 'required|min:8|max:12',
            'amount' => 'required|numeric|min:2.5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first(),
                'data' => null
            ], 400);
        }

        // Validate session with CodeIgniter system
        $sessionValidation = $this->bridgeService->validateSession($request->session_id);

        if (!$sessionValidation['valid']) {
            return response()->json([
                'status' => 'fail',
                'message' => $sessionValidation['message'],
                'data' => null
            ], 401);
        }

        try {
            $result = $this->derivService->processWithdrawal(
                $sessionValidation['data']['wallet_id'],
                $request->crNumber,
                $request->amount,
                $request->session_id
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Processing error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Process deposit callback from Deriv
     */
    public function processDeposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Request ID is required',
                'data' => null
            ], 400);
        }

        try {
            $result = $this->derivService->completeDeposit($request->request_id);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Processing error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Process withdrawal callback from Deriv
     */
    public function processWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Request ID is required',
                'data' => null
            ], 400);
        }

        try {
            $result = $this->derivService->completeWithdrawal($request->request_id);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Processing error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get transactions for a wallet
     */
    public function getTransactions($wallet_id)
    {
        try {
            $transactions = $this->bridgeService->getTransactions($wallet_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Transactions retrieved',
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve transactions: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
