<?php

namespace App\Services\Payment;

use App\Traits\ApiResponseHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MonnifyService
{
    use ApiResponseHandler;

    protected $baseUrl;
    protected $secretKey;
    protected $apiKey;
    protected $userLiveBaseUrl = false;
    protected $client;
    protected $authString;
    protected $tokenCacheKey = "monnify_access_token";
    protected $tokenCacheTime = 59.45;  //miniutes

    public function __construct()
    {
        if($this->userLiveBaseUrl === false){
            $this->baseUrl = config('services.monnify.sandbox_base_url');
        }else{
            $this->baseUrl = config('services.monnify.live_base_url');
        }

        $this->apiKey = config("services.monnify.api_key");
        $this->secretKey = config("services.monnify.secret_key");

        $this->authString = base64_encode("$this->apiKey:$this->secretKey");

        $this->client = new Client(['base_uri' =>  $this->baseUrl]);
    }


    // wallets
    public function createWallet(array $data){
        $response =  $this->makeApiRequest("/api/v1/disbursements/wallet", 'POST', $data);
        return $response;
    }

    // Get wallet details
    public function getWalletDetails($walletReference){
        $response = $this->makeApiRequest("/api/v1/disbursements/wallet/{$walletReference}", 'GET');
        return $response;
    }

    // Get wallet balance
    public function getWalletBalance($walletReference){
        $response = $this->makeApiRequest("/api/v1/disbursements/wallet-balance/{$walletReference}", 'GET');
        return $response;
    }

    // Reserved account methods
    public function createReservedAccount(array $data){
        $response = $this->makeApiRequest("/api/v2/bank-transfer/reserved-accounts", 'POST', $data);
        return $response;
    }

    public function getReservedAccountDetails($accountReference){
        $response = $this->makeApiRequest("/api/v2/bank-transfer/reserved-accounts/{$accountReference}", 'GET');
        return $response;
    }

    public function updateReservedAccountName($accountReference, $newName){
        $data = [
            'accountName' => $newName
        ];
        $response = $this->makeApiRequest("/api/v2/bank-transfer/reserved-accounts/{$accountReference}", 'PUT', $data);
        return $response;
    }

    public function deactivateReservedAccount($accountReference){
        $response = $this->makeApiRequest("/api/v2/bank-transfer/reserved-accounts/{$accountReference}/deactivate", 'PUT');
        return $response;
    }

    public function reactivateReservedAccount($accountReference){
        $response = $this->makeApiRequest("/api/v2/bank-transfer/reserved-accounts/{$accountReference}/activate", 'PUT');
        return $response;
    }

    public function getTransactionsByReference($reference){
        $response = $this->makeApiRequest("/api/v2/transactions/by-reference/{$reference}", 'GET');
        return $response;
    }

    public function initiatePayment($data){
        $response = $this->makeApiRequest("/api/v1/merchant/transactions/init-transaction", 'POST', $data);
        return $response;
    }

    public function verifyPayment($reference){
        $response = $this->makeApiRequest("/api/v2/merchant/transactions/query?paymentReference=$reference", 'GET');
        return $response;
    }

    protected function getAccessToken(){
        if (Cache::has($this->tokenCacheKey)) {
            $tokenData =  Cache::get($this->tokenCacheKey);

            if($tokenData && isset($tokenData['accessToken'], $tokenData['expiresAt'])){
                if ($tokenData['expiresAt'] > now()->timestamp) {
                    return $tokenData['accessToken'];
                }
            }
        }

        try{
            $response = $this->client->post("/api/v1/auth/login", [
                'headers' => [
                    'Authorization' => "Basic $this->authString",
                    'Content-Type' => "application/json"
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if($result['requestSuccessful'] && $result['responseMessage'] === "success"
                && isset($result['responseBody'],$result['responseBody']['accessToken']))
            {

                $accessToken = $result['responseBody']['accessToken'];
                $expiresIn = $result['responseBody']['expiresIn'];

                $expiresAt = now()->addSeconds($expiresIn)->timestamp;

                Cache::put($this->tokenCacheKey, [
                    'accessToken' => $accessToken,
                    'expiresAt' => $expiresAt
                ], $expiresIn / 60);

                return $accessToken;
            }else{
                return ApiResponseHandler::errorResponse('Failed to retrieve token', 500);
            }

        }catch(GuzzleException $e){
            Log::error('Monnify Service authentication error: ' . $e->getMessage());
            return ApiResponseHandler::errorResponse($e->getMessage(), 500);;
        }
    }

    protected function makeApiRequest($endpoint, $method = 'GET', $body = []){
        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->request($method, $endpoint, [
                'headers' => [
                    'Authorization' => "Bearer $accessToken",
                    'Content-Type' => 'application/json',
                ],
                'json' => $body
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return [
                "success" => false,
                "message" => $e->getMessage(),
            ];
        }
    }
}
