<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Traits\ApiResponseHandler;
use Illuminate\Support\Facades\DB;
use App\Repositories\WalletRepository;
use App\Services\Payment\MonnifyService;
use App\Services\Wallet\ReservedAccountService;
use App\Repositories\TransactionRepository;
use App\Repositories\ReservedAccountRepository;
use App\Services\ProfileService;

class WalletService
{
    private $paymentProvider;
    private $walletRepository;
    private $transactionRepository;
    private $reservedAccountRepository;
    private $reservedAccountService;
    private $merchantCode = 544657543870;
    private $contractCode;
    private $profileService;

    use ApiResponseHandler;

    public function __construct(
        MonnifyService $paymentProvider,
        ReservedAccountService $reservedAccountService,
        ProfileService $profileService,
        WalletRepository $walletRepository,
        TransactionRepository $transactionRepository,
        ReservedAccountRepository $reservedAccountRepository
    ){
        $this->paymentProvider = $paymentProvider;
        $this->reservedAccountService = $reservedAccountService;
        $this->walletRepository = $walletRepository;
        $this->profileService = $profileService;
        $this->transactionRepository = $transactionRepository;
        $this->reservedAccountRepository = $reservedAccountRepository;
        $this->contractCode = config('services.monnify.contract_code');
    }

    public function createWalletViaMonnify(User $user)
    {
        try {
            $walletReference = 'WAL_' . uniqid() . '_' . time();
            $walletName = $user->name . " Wallet - " . $walletReference;
            $customerName = $user->name;
            $customerEmail = $user->email;
            $customerBvn = $user->profile->bvn;
            $customerBvnDob = $user->profile->bvn_dob;

            $walletData = [
                "walletReference" => $walletReference,
                "walletName" => $walletName,
                "customerName" => $customerName,
                "customerEmail" => $customerEmail,
                "bvnDetails" => [
                    "bvn" => $customerBvn,
                    "dateOfBirth" => $customerBvnDob
                ]
            ];

            $createWalletResponse = $this->paymentProvider->createWallet($walletData);
            return $createWalletResponse;

            if (!isset($createWalletResponse['requestSuccessful']) || !$createWalletResponse['requestSuccessful']) {
                return [
                    "error" => "Failed to create wallet: " . ($createWalletResponse['responseMessage'] ?? 'Unknown error')
                ];
            }

            // Save wallet details to database
            $walletDetails = $createWalletResponse['responseBody'];
            $wallet = $this->walletRepository->create([
                'user_id' => $user->id,
                'wallet_reference' => $walletReference,
                'wallet_name' => $walletName,
                'wallet_id' => $walletDetails['walletId'] ?? null,
                'balance' => 0.00,
                'currency' => 'NGN',
                'status' => 'active',
                'meta_data' => json_encode($walletDetails)
            ]);

            return [
                'success' => true,
                'wallet' => $wallet,
                'wallet_details' => $walletDetails
            ];

        } catch (\Throwable $th) {
            return [
                "error" => $th->getMessage(),
                "file" => $th->getFile(),
                "line" => $th->getLine()
            ];
        }
    }

    public function createHybridWalletSystem(User $user)
    {
        DB::beginTransaction();
        try {

            $walletResult = $this->createWalletViaMonnify($user);

            if (isset($walletResult['error'])) {
                DB::rollBack();
                return [
                    "error" => $walletResult['error']
                ];
            }

            $accountResult = $this->reservedAccountService->createReservedAccount($user);

            if (isset($accountResult['error'])) {
                DB::rollBack();
                return [
                    "error" => $accountResult['error']
                ];
            }

            $this->walletRepository->update($walletResult['wallet'], [
                'reserved_account_id' => $accountResult['reserved_account']->id
            ]);

            DB::commit();

            return [
                'success' => true,
                'wallet' => $walletResult['wallet'],
                'reserved_account' => $accountResult['reserved_account'],
                'wallet_details' => $walletResult['wallet_details'],
                'account_details' => $accountResult['account_details']
            ];

        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "error" => $th->getMessage(),
                "file" => $th->getFile(),
                "line" => $th->getLine()
            ];
        }
    }

    public function initiateWalletFunding(User $user, float $amount): array
    {
        try {
            $reference = 'REF_' . uniqid() . '_' . time();

            $transaction = $this->transactionRepository->create([
                'user_id' => $user->id,
                'type' => 'wallet_funding',
                'amount' => $amount,
                'reference' => $reference,
                'status' => 'pending'
            ]);

            $paymentData = [
                'customerEmail' => $user->email,
                'amount' => $amount .".00",
                'paymentReference' => $reference,
                'currencyCode' => "NGN",
                'contractCode' => $this->merchantCode,
                'metaData' => [
                    'user_id' => $user->id,
                    'type' => 'wallet_funding'
                ]
            ];

            $paymentResponse = $this->paymentProvider->initiatePayment($paymentData);

            return [
                'payment_response' => $paymentResponse,
                'reference' => $reference,
                'transaction' => $transaction
            ];
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function processWalletFunding(string $reference)
    {
        $response = $this->paymentProvider->verifyPayment($reference);

        if (!$response['requestSuccessful'] && $response['responseMessage'] !== 'success') {
            return [
                "error" =>"Payment verification failed"
            ];
        }

        DB::beginTransaction();
        try {
            $transaction = $this->transactionRepository->find($reference);

            if (!$transaction || $transaction->status !== 'pending') {
                return ApiResponseHandler::errorResponse("Invalid transaction");
            }

            // Update transaction
            $this->transactionRepository->update($transaction, [
                'status' => 'completed',
                'provider_reference' => $response['responseBody']['transactionReference'],
                'meta_data' => json_encode($response['responseBody'])
            ]);

            // Credit user wallet
            $this->creditWallet(
                $transaction->user,
                $transaction->amount,
                "Wallet funding via " . $response['responseBody']['paymentMethod']
            );

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" =>$e->getMessage()
            ];
        }
    }

    public function debitWallet(User $user, float $amount, string $description)
    {
        DB::beginTransaction();
        try {
            $wallet = $this->walletRepository->findByUser($user);

            if ($wallet->balance < $amount) {
                return [
                    "error" =>"Insufficient wallet balance"
                ];
            }

            $this->walletRepository->update($wallet, [
                'balance' => $wallet->balance - $amount
            ]);

            $this->walletRepository->createTransaction($wallet, [
                'type' => 'debit',
                'amount' => $amount,
                'description' => $description,
                'previous_balance' => $wallet->balance,
                'current_balance' => $wallet->balance - $amount
            ]);

            $this->profileService->syncAccountDataToProfile($user);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" =>  $e->getMessage()
            ];
        }
    }

    public function creditWallet(User $user, float $amount, string $description)
    {
        DB::beginTransaction();
        try {
            $wallet = $this->walletRepository->findByUser($user);

            // Update wallet balance
            $this->walletRepository->update($wallet, [
                'balance' => $wallet->balance + $amount
            ]);

            // Create wallet transaction
            $this->walletRepository->createTransaction($wallet, [
                'type' => 'credit',
                'amount' => $amount,
                'description' => $description,
                'previous_balance' => $wallet->balance,
                'current_balance' => $wallet->balance + $amount
            ]);

            $this->profileService->syncAccountDataToProfile($user);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" =>  $e->getMessage()
            ];
        }
    }

    public function getBalance(User $user): float
    {
        $wallet = $this->walletRepository->findByUser($user);
        return $wallet->balance;
    }

    public function getTransactions(User $user, array $filters = [])
    {
        return $this->walletRepository->getTransactions($user, $filters);
    }


    public function processVtuServicePayment(User $user, float $amount, string $serviceType, array $serviceData = [])
    {
        DB::beginTransaction();
        try {
            // First debit the user's wallet
            $debitResult = $this->debitWallet($user, $amount, "Payment for {$serviceType} service");

            if (isset($debitResult['error'])) {
                return $debitResult;
            }

            $reference = 'VTU_' . strtoupper($serviceType) . '_' . uniqid() . '_' . time();
            $transaction = $this->transactionRepository->create([
                'user_id' => $user->id,
                'type' => 'vtu_service',
                'amount' => $amount,
                'reference' => $reference,
                'status' => 'processing',
                'service_type' => $serviceType,
                'meta_data' => json_encode($serviceData)
            ]);

            // Here you would integrate with your VTU provider API
            // This is a placeholder for that integration
            $vtuResult = $this->processVtuWithProvider($serviceType, $amount, $serviceData, $reference);

            if (isset($vtuResult['error'])) {
                // If the VTU service fails, refund the user
                $this->creditWallet(
                    $user,
                    $amount,
                    "Refund for failed {$serviceType} service"
                );

                $this->transactionRepository->update($transaction, [
                    'status' => 'failed',
                    'meta_data' => json_encode(array_merge(
                        json_decode($transaction->meta_data, true) ?? [],
                        ['vtu_response' => $vtuResult]
                    ))
                ]);

                DB::rollBack();
                return $vtuResult;
            }

            // Update the transaction to completed
            $this->transactionRepository->update($transaction, [
                'status' => 'completed',
                'provider_reference' => $vtuResult['reference'] ?? null,
                'meta_data' => json_encode(array_merge(
                    json_decode($transaction->meta_data, true) ?? [],
                    ['vtu_response' => $vtuResult]
                ))
            ]);

            DB::commit();
            return [
                'success' => true,
                'transaction' => $transaction,
                'service_result' => $vtuResult
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" => $e->getMessage()
            ];
        }
    }

    protected function processVtuWithProvider($serviceType, $amount, $serviceData, $reference)
    {
        // This is a placeholder function
        // In a real implementation, you would call your VTU provider API here

        // For testing purposes, return success
        return [
            'success' => true,
            'reference' => 'PROVIDER_' . $reference,
            'message' => $serviceType . ' service processed successfully'
        ];

        // For error simulation:
        // return [
        //     "error" => "Service provider unavailable"
        // ];
    }
}
