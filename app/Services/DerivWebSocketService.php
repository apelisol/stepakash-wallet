<?php

namespace App\Services;

use WebSocket\Client;
use Illuminate\Support\Facades\Log;

class DerivWebSocketService
{
    private $appId;
    private $token;
    private $endpoint;

    public function __construct()
    {
        $this->appId = config('services.deriv.app_id');
        $this->token = config('services.deriv.token');
        $this->endpoint = config('services.deriv.endpoint');
    }

    public function transferToAccount($loginId, $amount)
    {
        try {
            $url = "wss://{$this->endpoint}/websockets/v3?app_id={$this->appId}";

            $client = new Client($url, [
                'timeout' => 60,
                'headers' => ['Content-Type' => 'application/json']
            ]);

            // Authorize
            $client->send(json_encode(["authorize" => $this->token]));
            $authResponse = json_decode($client->receive(), true);

            if (isset($authResponse['error'])) {
                $client->close();
                return [
                    'success' => false,
                    'message' => 'Authorization failed: ' . $authResponse['error']['message'],
                    'data' => null
                ];
            }

            // Transfer
            $transferRequest = [
                "paymentagent_transfer" => 1,
                "transfer_to" => $loginId,
                "amount" => $amount,
                "currency" => "USD",
                "description" => "Deposit via Stepakash Laravel Service"
            ];

            $client->send(json_encode($transferRequest));
            $transferResponse = json_decode($client->receive(), true);
            $client->close();

            if (isset($transferResponse['error'])) {
                return [
                    'success' => false,
                    'message' => $transferResponse['error']['message'],
                    'data' => $transferResponse
                ];
            }

            return [
                'success' => isset($transferResponse['paymentagent_transfer']) && $transferResponse['paymentagent_transfer'] == 1,
                'message' => 'Transfer successful',
                'data' => $transferResponse
            ];
        } catch (\Exception $e) {
            Log::error('Deriv WebSocket error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
