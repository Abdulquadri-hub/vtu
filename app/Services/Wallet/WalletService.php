<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Traits\ApiResponseHandler;
use Illuminate\Support\Facades\DB;
use App\Repositories\WalletRepository;
use App\Services\Payment\MonnifyService;
use App\Repositories\TransactionRepository;

class WalletService
{
    private $paymentProvider ;
    private $walletRepository;
    private $transactionRepository;
    private $merchantCode = 544657543870;

    use ApiResponseHandler;

    public function __construct(MonnifyService $paymentProvider,WalletRepository $walletRepository,TransactionRepository $transactionRepository){
        $this->paymentProvider = $paymentProvider;
        $this->walletRepository = $walletRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function createWalletViaMonnify(){
        //
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
                'currencyCode ' => "NGN",
                'contractCode ' => $this->merchantCode,
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
}

