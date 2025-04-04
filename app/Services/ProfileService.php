<?php

namespace App\Services;

use App\Models\User;
use App\Models\Profile;
use App\Services\Wallet\WalletService;
use App\Repositories\WalletRepository;
use App\Repositories\ReservedAccountRepository;
use Illuminate\Support\Facades\DB;
use App\Services\Auth\ValidationService;

class ProfileService
{
    protected $walletService;
    protected $walletRepository;
    protected $reservedAccountRepository;
    protected $validateService;

    public function __construct(
        WalletService $walletService,
        WalletRepository $walletRepository,
        ReservedAccountRepository $reservedAccountRepository,
        ValidationService $validateService
    ) {
        $this->walletService = $walletService;
        $this->walletRepository = $walletRepository;
        $this->validateService = $validateService;
        $this->reservedAccountRepository = $reservedAccountRepository;
    }

    public function createOrUpdateProfile($user, array $data)
    {
        DB::beginTransaction();
        try {

            $validation = $this->validateService->validateProfile($data);

            if ($validation !== true) {
                return $validation;
            }

            $profile = Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bvn' => $data['bvn'] ?? null,
                    'nin' => $data['nin'] ?? null,
                    'bvn_dob' => $data['bvn_dob'] ?? null,
                    'profile_picture' => $data['profile_picture'] ?? null,
                ]
            );


            if (!$user->has_profile) {
                $walletResult = $this->walletService->createHybridWalletSystem($user);

                if (isset($walletResult['error'])) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => $walletResult['error']
                    ];
                }

                // Update user has_profile status
                $user->has_profile = true;
                $user->save();

                $this->syncAccountDataToProfile($user);
            }

            DB::commit();

            return [
                'success' => true,
                'profile' => $profile,
                'message' => 'Profile updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ];
        }
    }


    public function syncAccountDataToProfile(User $user)
    {
        $profile = $user->profile;
        if (!$profile) return false;

        $wallet = $this->walletRepository->findByUser($user);
        $reservedAccount = $this->reservedAccountRepository->findByUser($user);

        if ($wallet) {
            $profile->update([
                'wallet_reference' => $wallet->wallet_reference,
                'wallet_balance' => $wallet->balance,
                'wallet_status' => $wallet->status,
            ]);
        }

        if ($reservedAccount) {

            if (is_array($reservedAccount)) {
                $reservedAccount = $reservedAccount[0];
            }

            $profile->update([
                'account_name' => $reservedAccount->account_name,
                'account_number' => $reservedAccount->account_number,
                'bank_name' => $reservedAccount->bank_name,
                'bank_code' => $reservedAccount->bank_code,
            ]);
        }

        return true;
    }
}
