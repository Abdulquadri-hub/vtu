<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            return response()->json([
                'status' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
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
            ]
        ]);
    }
}
