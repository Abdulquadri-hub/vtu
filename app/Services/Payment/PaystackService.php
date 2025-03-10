<?php

namespace App\Services\Payment;

use App\Traits\ApiResponseHandler;
use Illuminate\Support\Facades\Http;

class PaystackService 
{
    use ApiResponseHandler;

    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
    }

    public function initiatePayment(array $data): array
    {

        $url = "{$this->baseUrl}/transaction/initialize";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secretKey}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'email' => $data['email'],
            'amount' => $data['amount'] * 100,
            'reference' => $data['reference'],
            'callback_url' => $data['callback_url'],
            'metadata' => [
                'user_id' => $data['user_id'],
                'type' => 'wallet_funding'
            ]
        ]);

        if (!$response->successful()) {
          return $this->errorResponse('Failed to initiate payment', 442);
        }

        return $response->json();
    }

    public function verifyPayment(string $reference): array
    {
        $url = "{$this->baseUrl}/transaction/verify/{$reference}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secretKey}",
            'Content-Type' => 'application/json',
        ])->get($url);

        if (!$response->successful()) {
            return $this->errorResponse('Failed to verify payment', 442);
        }

        return $response->json();
    }

    public function getPaymentLink(array $data): string
    {
        $response = $this->initiatePayment($data);
        return $response['data']['authorization_url'];
    }
}
