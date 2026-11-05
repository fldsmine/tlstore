<?php

namespace App\Features\Payments\Http\Controllers\Api\V1;

use App\Application\Actions\Payments\SubmitManualDeposit;
use App\Application\Actions\Payments\InitializeGatewayTopup;
use App\Application\Actions\Payments\ProcessGatewayWebhook;
use App\Application\Actions\Payments\VerifyGatewayTopup;
use App\Enums\PaymentIntentStatus;
use App\Models\ManualDepositSubmission;
use App\Models\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class TopupController
{
    public function initializeGateway(Request $request, InitializeGatewayTopup $initialize): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:100'],
            'currency' => ['required', 'string', 'size:3'],
            'gateway' => ['required', 'in:korapay,paystack,flutterwave,bachs'],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'customer_email' => ['nullable', 'string', 'max:191', 'email:rfc,dns'],
        ]);

        return response()->json(['data' => $initialize->handle(
            $request->user(), $data['amount_minor'], strtoupper($data['currency']), $data['gateway'], $data['idempotency_key'],
            customerEmail: $data['customer_email'] ?? null,
        )], 201);
    }

    public function status(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        abort_unless($paymentIntent->user_id === $request->user()->id && $paymentIntent->purpose === 'topup', 404);

        return response()->json(['data' => [
            'id' => $paymentIntent->id,
            'status' => $paymentIntent->status->value,
            'amount_minor' => $paymentIntent->amount_minor,
            'currency' => $paymentIntent->currency,
            'provider' => $paymentIntent->provider,
            'paid_at' => $paymentIntent->paid_at,
        ]]);
    }

    public function check(Request $request, PaymentIntent $paymentIntent, VerifyGatewayTopup $verify): JsonResponse
    {
        abort_unless($paymentIntent->user_id === $request->user()->id && $paymentIntent->purpose === 'topup', 404);
        $paymentIntent = $verify->handle($paymentIntent);

        return response()->json(['data' => [
            'id' => $paymentIntent->id,
            'status' => $paymentIntent->status->value,
            'amount_minor' => $paymentIntent->amount_minor,
            'currency' => $paymentIntent->currency,
            'provider' => $paymentIntent->provider,
            'paid_at' => $paymentIntent->paid_at,
        ]]);
    }

    public function cancel(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        abort_unless($paymentIntent->user_id === $request->user()->id && $paymentIntent->purpose === 'topup', 404);
        abort_if($paymentIntent->provider === 'manual', 422, 'Manual deposit requests cannot be cancelled here.');
        abort_if($paymentIntent->status === PaymentIntentStatus::Paid, 409, 'A completed payment cannot be cancelled.');
        abort_unless(in_array($paymentIntent->status, [PaymentIntentStatus::Pending, PaymentIntentStatus::Expired], true), 409, 'This payment request can no longer be cancelled.');

        if ($paymentIntent->status === PaymentIntentStatus::Pending) {
            $paymentIntent->forceFill(['status' => 'expired', 'expires_at' => now()])->save();
        }

        $paymentIntent->refresh();

        return response()->json(['data' => [
            'id' => $paymentIntent->id,
            'status' => $paymentIntent->status->value,
            'expires_at' => $paymentIntent->expires_at,
        ], 'message' => 'The top-up checkout request has been expired.']);
    }

    public function topups(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->paymentIntents()
            ->where('purpose', 'topup')->latest()->paginate(30)]);
    }

    public function webhook(string $provider, Request $request, ProcessGatewayWebhook $process): JsonResponse
    {
        abort_unless(in_array($provider, ['korapay', 'paystack', 'flutterwave', 'bachs'], true), 404);
        $process->handle($provider, $request);

        return response()->json(['received' => true]);
    }

    public function submitManualDeposit(Request $request, SubmitManualDeposit $submit): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:100'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'bank_reference' => ['nullable', 'string', 'max:191'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $proofPath = $data['proof']->store('manual-deposit-proofs/'.$request->user()->id);
        $deposit = $submit->handle(
            $request->user(), $data['amount_minor'], $data['currency'], $data['idempotency_key'],
            $proofPath, $data['bank_reference'] ?? null,
        );
        if ($deposit->proof_storage_path !== $proofPath) {
            Storage::disk('local')->delete($proofPath);
        }

        return response()->json(['data' => $deposit, 'message' => 'Deposit evidence submitted for admin approval.'], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->manualDeposits()->latest('submitted_at')->paginate(30)]);
    }
}
