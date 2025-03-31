<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ApiResponseHandler;
use App\Http\Controllers\Controller;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{

    use ApiResponseHandler;

    private $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function initiateFunding(Request $request)
    {

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100'
        ]);

        try {
            $result = $this->walletService->initiateWalletFunding(
                $request->user(),
                $validated['amount']
            );

            if(!$result['success']){
                return ApiResponseHandler::errorResponse("Error funding wallet", 400, $result);
            }
            return ApiResponseHandler::successResponse($result, "Wallet Funded Successfully");

        } catch (\Exception $e) {
            return ApiResponseHandler::errorResponse($e->getMessage());
        }
    }

    public function handleCallback(Request $request)
    {
        try {
            $reference = $request->input('reference');
            $response = $this->walletService->processWalletFunding($reference);
            return ApiResponseHandler::successResponse($response);
        } catch (\Exception $e) {
            return ApiResponseHandler::errorResponse('Failed to process funding: '. $e->getMessage(), 500);
        }
    }

    public function getBalance(Request $request)
    {
        $balance = $this->walletService->getBalance($request->user());

        return response()->json([
            'balance' => $balance
        ]);
    }

    public function getTransactions(Request $request)
    {
        $filters = $request->validate([
            'type' => 'nullable|in:credit,debit',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $transactions = $this->walletService->getTransactions(
            $request->user(),
            $filters
        );

        return ApiResponseHandler::successResponse($transactions);
    }
}

