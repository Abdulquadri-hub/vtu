<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Traits\ApiResponseHandler;
use Illuminate\Support\Facades\DB;
use App\Repositories\WalletRepository;
use App\Services\Payment\MonnifyService;
use App\Repositories\ReservedAccountRepository;

class ReservedAccountService
{
    private $paymentProvider;
    private $walletRepository;
    private $reservedAccountRepository;
    private $contractCode;

    use ApiResponseHandler;

    public function __construct(
        MonnifyService $paymentProvider,
        WalletRepository $walletRepository,
        ReservedAccountRepository $reservedAccountRepository
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->walletRepository = $walletRepository;
        $this->reservedAccountRepository = $reservedAccountRepository;
        $this->contractCode = config('services.monnify.contract_code');
    }

    public function createReservedAccount(User $user)
    {
        $existingAccount = $this->reservedAccountRepository->findByUser($user);
        if ($existingAccount) {
            return [
                'status' => true,
                'data' => $existingAccount,
                'message' => 'Reserved account already exists'
            ];
        }

        $wallet = $this->walletRepository->findByUser($user);
        if (!$wallet) {
            return [
                'status' => false,
                'message' => 'User wallet not found'
            ];
        }

        $reference = 'RESACC_' . uniqid() . '_' . time();

        $accountData = [
            'accountReference' => $reference,
            'accountName' => $user->name,
            'currencyCode' => 'NGN',
            'contractCode' => $this->contractCode,
            'customerEmail' => $user->email,
            'customerName' => $user->name,
            'bvn' => $user->bvn ?? null,
            'nin' => $user->bvn ?? null,
            'getAllAvailableBanks' => true,
            // 'preferredBanks' => ['035', '232'] // Example: GTB, Sterling
        ];

        // Create reserved account on Monnify
        $response = $this->paymentProvider->createReservedAccount($accountData);

        if (!isset($response['requestSuccessful']) || !$response['requestSuccessful']) {
            return [
                'status' => false,
                'message' => 'Failed to create reserved account',
                'error' => $response['responseMessage'] ?? 'Unknown error'
            ];
        }

        DB::beginTransaction();
        try {

            $accountInfo = $response['responseBody'];
            $accounts = $accountInfo['accounts'] ?? [];

            foreach ($accounts as $account) {
                $this->reservedAccountRepository->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'reference' => $reference,
                    'account_name' => $account['accountName'],
                    'account_number' => $account['accountNumber'],
                    'bank_name' => $account['bankName'],
                    'bank_code' => $account['bankCode'],
                    'provider' => 'monnify',
                    'provider_reference' => $accountInfo['reservationReference'],
                    'meta_data' => json_encode($accountInfo)
                ]);
            }

            DB::commit();

            return [
                'status' => true,
                'data' => $this->reservedAccountRepository->findByUser($user),
                'message' => 'Reserved account created successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => false,
                'message' => 'Failed to save reserved account',
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAllReservedAccounts(User $user)
    {
        return $this->reservedAccountRepository->findByUser($user);
    }

    public function deactivateReservedAccount(User $user)
    {
        try {
            $reservedAccount = $this->reservedAccountRepository->findByUser($user);

            if (!$reservedAccount) {
                return [
                    "error" => "Reserved account not found"
                ];
            }

            $response = $this->paymentProvider->deactivateReservedAccount($reservedAccount->account_reference);

            if (!isset($response['requestSuccessful']) || !$response['requestSuccessful']) {
                return [
                    "error" => "Failed to deactivate reserved account: " . ($response['responseMessage'] ?? 'Unknown error')
                ];
            }

            $this->reservedAccountRepository->update($reservedAccount, [
                'status' => 'inactive'
            ]);

            return [
                'success' => true,
                'message' => 'Reserved account deactivated successfully'
            ];
        } catch (\Exception $e) {
            return [
                "error" => $e->getMessage()
            ];
        }
    }

    public function processReservedAccountFunding($payload)
    {
        // Validate payload
        if (!isset($payload['eventType']) || $payload['eventType'] !== 'SUCCESSFUL_TRANSACTION') {
            return [
                'status' => false,
                'message' => 'Invalid event type'
            ];
        }

        if (!isset($payload['eventData']['product']) || $payload['eventData']['product'] !== 'RESERVED_ACCOUNT') {
            return [
                'status' => false,
                'message' => 'Invalid product type'
            ];
        }

        DB::beginTransaction();
        try {
            $eventData = $payload['eventData'];
            $amountPaid = $eventData['amountPaid'];
            $accountNumber = $eventData['destinationAccountInformation']['accountNumber'];
            $reference = $eventData['transactionReference'];

            // Find the reserved account
            $reservedAccount = $this->reservedAccountRepository->findByAccountNumber($accountNumber);
            if (!$reservedAccount) {
                DB::rollBack();
                return [
                    'status' => false,
                    'message' => 'Reserved account not found'
                ];
            }

            $user = $reservedAccount->user;
            $wallet = $reservedAccount->wallet;

            // Check if transaction already exists
            $existingTransaction = DB::table('transactions')
                ->where('provider_reference', $reference)
                ->first();

            if ($existingTransaction) {
                DB::rollBack();
                return [
                    'status' => true,
                    'message' => 'Transaction already processed'
                ];
            }

            // Create transaction record
            $transactionId = DB::table('transactions')->insertGetId([
                'user_id' => $user->id,
                'reference' => 'FUND_' . uniqid() . '_' . time(),
                'type' => 'wallet_funding',
                'amount' => $amountPaid,
                'status' => 'completed',
                'provider' => 'monnify',
                'provider_reference' => $reference,
                'meta_data' => json_encode($eventData),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update wallet balance
            $this->walletRepository->update($wallet, [
                'balance' => $wallet->balance + $amountPaid
            ]);

            // Create wallet transaction
            $this->walletRepository->createTransaction($wallet, [
                'type' => 'credit',
                'amount' => $amountPaid,
                'description' => 'Wallet funding via reserved account',
                'previous_balance' => $wallet->balance,
                'current_balance' => $wallet->balance + $amountPaid,
                'reference' => $reference
            ]);

            DB::commit();

            return [
                'status' => true,
                'message' => 'Funding processed successfully',
                'data' => [
                    'transaction_id' => $transactionId,
                    'amount' => $amountPaid
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => false,
                'message' => 'Failed to process funding',
                'error' => $e->getMessage()
            ];
        }
    }
}
