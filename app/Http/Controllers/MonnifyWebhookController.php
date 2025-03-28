<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Wallet\ReservedAccountService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonnifyWebhookController extends Controller
{
    private $walletService;
    private $reservedAccountService;

    public function __construct(
        WalletService $walletService,
        ReservedAccountService $reservedAccountService
    ) {
        $this->walletService = $walletService;
        $this->reservedAccountService = $reservedAccountService;
    }

    public function handle(Request $request)
    {
        // Verify webhook signature if provided
        $signature = $request->header('monnify-signature');
        if ($signature) {
            if (!$this->verifySignature($request, $signature)) {
                Log::warning('Invalid Monnify webhook signature');
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
            }
        }

        // Get the webhook payload
        $payload = $request->all();

        // Log the webhook for debugging
        Log::info('Monnify webhook received', ['payload' => $payload]);

        // Process based on event type
        if (!isset($payload['eventType'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid event payload'], 400);
        }

        switch ($payload['eventType']) {
            case 'SUCCESSFUL_TRANSACTION':
                // Handle successful payment notification
                if (isset($payload['eventData']['product'])) {
                    switch ($payload['eventData']['product']) {
                        case 'RESERVED_ACCOUNT':
                            // Process reserved account funding
                            $result = $this->reservedAccountService->processReservedAccountFunding($payload);
                            break;

                        case 'PAYMENT_GATEWAY':
                            // Process payment gateway transaction (wallet top-up via payment link)
                            $reference = $payload['eventData']['transactionReference'] ?? null;
                            if ($reference) {
                                $result = $this->walletService->processWalletFunding($reference);
                            } else {
                                $result = ['status' => false, 'message' => 'Transaction reference not found'];
                            }
                            break;

                        default:
                            $result = ['status' => false, 'message' => 'Unsupported product type'];
                    }
                } else {
                    $result = ['status' => false, 'message' => 'Product information not found'];
                }
                break;

            case 'FAILED_TRANSACTION':
                // Just log failed transactions for now
                Log::info('Failed transaction notification', ['data' => $payload]);
                $result = ['status' => true, 'message' => 'Failed transaction logged'];
                break;

            default:
                // Unhandled event type
                Log::info('Unhandled webhook event type', ['eventType' => $payload['eventType']]);
                $result = ['status' => false, 'message' => 'Unhandled event type'];
        }

        // Always return 200 OK to acknowledge receipt of webhook
        return response()->json([
            'status' => $result['status'] ? 'success' : 'error',
            'message' => $result['message']
        ]);
    }

    /**
     * Verify the webhook signature
     *
     * @param Request $request
     * @param string $signature
     * @return bool
     */
    private function verifySignature(Request $request, string $signature): bool
    {
        $secretKey = config('services.monnify.secret_key');
        $payload = $request->getContent();

        $computedSignature = hash_hmac('sha512', $payload, $secretKey);

        return hash_equals($computedSignature, $signature);
    }
}
