<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ProfileService;
use App\Traits\ApiResponseHandler;
use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    private $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function create(Request $request){
        try {

            $result = $this->profileService->createOrUpdateProfile($request->user(), $request->all());
            if($result['success']){
                return ApiResponseHandler::successResponse($result);
            }

            return ApiResponseHandler::errorResponse("Error creating profile",400, $result);
        } catch (\Throwable $th) {
            return ApiResponseHandler::errorResponse($th->getMessage());
        }
    }

    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();
            $profile = $user->profile;

            if (!$profile) {
                return ApiResponseHandler::errorResponse('Profile not found', 404);
            }

            return ApiResponseHandler::successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'profile' => [
                    'bvn' => $profile->bvn,
                    'nin' => $profile->nin,
                    'wallet_balance' => $profile->wallet_balance,
                    'formatted_balance' => $profile->formatted_balance,
                    'account_details' => [
                        'account_name' => $profile->account_name,
                        'account_number' => $profile->account_number,
                        'bank_name' => $profile->bank_name,
                    ]
                ]
            ]);
        } catch (\Throwable $th) {
            return ApiResponseHandler::errorResponse($th->getMessage());
        }
    }
}
