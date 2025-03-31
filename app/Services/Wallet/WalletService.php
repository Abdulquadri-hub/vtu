<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Traits\ApiResponseHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Container\Container;

use function PHPSTORM_META\type;

class WalletService
{
    use ApiResponseHandler;

    private $container;
    private $merchantCode = 544657543870;
    private $contractCode;

    // Lazily loaded dependencies
    private $paymentProvider;
    private $walletRepository;
    private $transactionRepository;
    private $reservedAccountService;
    private $profileService;

    public function __construct(Container $container)
    {
        $this->container = $container;
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
            $createWalletResponse = $this->getPaymentProvider()->createWallet($walletData);
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

            $accountResult = $this->getReservedAccountService()->createReservedAccount($user);

            if (isset($accountResult['error'])) {
                DB::rollBack();
                return [
                    "error" => $accountResult['error']
                ];
            }

            $this->getWalletRepository()->update($walletResult['wallet'], [
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

            if(!$user->has_profile){
                return [
                    "success" => false,
                    "message" => "Complete your profile"
                ];
            }

            $reference = 'TXNREF_' . uniqid() . '_' . time();

            $transaction = $this->getTransactionRepository()->create([
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
                'contractCode' => $this->contractCode,
                'metaData' => [
                    'user_id' => $user->id,
                    'type' => 'wallet_funding'
                ]
            ];

            $paymentResponse = $this->getPaymentProvider()->initiatePayment($paymentData);

            return [
                'payment_response' => $paymentResponse,
                'reference' => $reference,
            ];
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function processWalletFunding(string $reference)
    {
        try {
            DB::beginTransaction();

            $response = $this->getPaymentProvider()->verifyPayment($reference);


            if (!$response['requestSuccessful'] || $response['responseMessage'] !== 'success') {
                return [
                    "success" => false,
                    "message" => $response['message'] ?? "Payment verification failed"
                ];
            }

            $transaction = $this->getTransactionRepository()->find($reference);

            if (!$transaction || $transaction->status !== 'pending') {
                return [
                    "success" => false,
                    "message" => "Invalid transaction"
                ];
            }


            if ($transaction->status === strtolower($response['responseBody']['paymentStatus'])) {
                DB::rollBack();
                return [
                    "success" => false,
                    "message" => "Transaction already processed"
                ];
            }

            $this->getTransactionRepository()->update($transaction, [
                'status' => strtolower($response['responseBody']['paymentStatus']),
                'provider_reference' => $response['responseBody']['transactionReference'],
                'meta_data' => json_encode($response['responseBody'])
            ]);

            if (strtolower($response['responseBody']['paymentStatus']) === 'paid') {
                $this->creditWallet(
                    $transaction->user,
                    $response['responseBody']['amountPaid'],
                    "Wallet funding via " . $response['responseBody']['paymentMethod']
                );
            }

            DB::commit();
            return $response;
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    public function debitWallet(User $user, float $amount, string $description)
    {
        DB::beginTransaction();
        try {
            $wallet = $this->getWalletRepository()->findByUser($user);

            $previousBalance = $wallet->balance;

            if ($previousBalance < $amount) {
                return [
                    "error" => "Insufficient wallet balance"
                ];
            }

            $newBalance = $previousBalance - $amount;

            $this->getWalletRepository()->update($wallet, [
                'balance' => $newBalance
            ]);

            $this->getWalletRepository()->createTransaction($wallet, [
                'type' => 'debit',
                'amount' => $amount,
                'description' => $description,
                'previous_balance' => $previousBalance,
                'current_balance' => $newBalance
            ]);

            $this->getProfileService()->profileService->syncAccountDataToProfile($user);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" => $e->getMessage()
            ];
        }
    }

    public function creditWallet(User $user, float $amount, string $description = ''): void
    {
        DB::transaction(function () use ($user, $amount, $description) {
            $wallet = $user->wallet;

            $previousBalance = $wallet->balance;

            $wallet->balance += $amount;
            $wallet->save();

            $wallet->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $wallet->balance,
                'description' => $description ?: 'Wallet funding',
            ]);
        });
    }

    public function getBalance(User $user): float
    {
        $wallet = $this->getWalletRepository()->findByUser($user);
        return $wallet->balance;
    }

    public function getTransactions(User $user, array $filters = [])
    {
        return $this->getWalletRepository()->getTransactions($user, $filters);
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
        return [
            "error" => "Service provider unavailable"
        ];
    }

    private function getPaymentProvider()
    {
           if (!$this->paymentProvider) {
               $this->paymentProvider = $this->container->make(\App\Services\Payment\MonnifyService::class);
           }
           return $this->paymentProvider;
    }

    private function getWalletRepository()
    {
        if (!$this->walletRepository) {
            $this->walletRepository = $this->container->make(\App\Repositories\WalletRepository::class);
        }
        return $this->walletRepository;
    }

    private function getTransactionRepository()
    {
        if (!$this->transactionRepository) {
            $this->transactionRepository = $this->container->make(\App\Repositories\TransactionRepository::class);
        }
        return $this->transactionRepository;
    }

    private function getReservedAccountService()
    {
        if (!$this->reservedAccountService) {
            $this->reservedAccountService = $this->container->make(\App\Services\Wallet\ReservedAccountService::class);
        }
        return $this->reservedAccountService;
    }

    private function getProfileService()
    {
        if (!$this->profileService) {
            $this->profileService = $this->container->make(\App\Services\ProfileService::class);
        }
        return $this->profileService;
    }
}
