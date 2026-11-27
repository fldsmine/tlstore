<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Application\Payments\GatewayBalances;
use App\Application\Payments\RevalidateGatewayPayment;
use App\Application\Payments\SettleGatewayPaymentManually;
use App\Models\Admin;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * JSON parity for the console's gateway-payments section, used by the mobile
 * admin app. Same rules, same domain actions as the Blade screens.
 */
final class AdminGatewayPaymentController
{
    private const ROLES = ['super_admin', 'admin', 'finance'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize($request);

        $filters = $request->validate([
            'provider' => ['nullable', Rule::in(SettleGatewayPaymentManually::GATEWAY_PROVIDERS)],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'cancelled', 'expired', 'refunded'])],
            'purpose' => ['nullable', Rule::in(['topup', 'subscription', 'package_addon'])],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PaymentIntent::query()
            ->with(['user:id,full_name,username,email'])
            ->whereIn('provider', SettleGatewayPaymentManually::GATEWAY_PROVIDERS);

        foreach (['provider', 'status', 'purpose'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['q'])) {
            $needle = '%'.trim($filters['q']).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->where('provider_reference', 'like', $needle)
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', $needle)
                        ->orWhere('username', 'like', $needle));
            });
        }

        return response()->json($query->latest('id')->paginate((int) ($filters['per_page'] ?? 25)));
    }

    public function show(Request $request, int $intent): JsonResponse
    {
        $this->authorize($request);
        $paymentIntent = $this->gatewayIntent($intent);

        return response()->json(['data' => [
            'intent' => $paymentIntent->load(['user:id,full_name,username,email']),
            'transaction' => Transaction::query()->where('reference', 'payment-intent:'.$paymentIntent->id)->first(),
            'ledger_entries' => $paymentIntent->ledgerEntries()->get(),
        ]]);
    }

    public function revalidate(Request $request, int $intent, RevalidateGatewayPayment $revalidate): JsonResponse
    {
        $this->authorize($request);

        try {
            $result = $revalidate->handle($this->admin($request), $this->gatewayIntent($intent));
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse($exception);
        }

        return response()->json([
            'outcome' => $result['outcome'],
            'status' => $result['status'],
            'message' => $result['message'],
            'data' => $result['intent'],
        ], $result['outcome'] === 'error' ? 502 : 200);
    }

    public function decide(Request $request, int $intent, SettleGatewayPaymentManually $settle): JsonResponse
    {
        $this->authorize($request);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'provider_reference' => ['nullable', 'string', 'max:191'],
        ]);

        $paymentIntent = $this->gatewayIntent($intent);

        try {
            $settled = $settle->handle(
                $this->admin($request),
                $paymentIntent,
                $data['decision'],
                $data['note'] ?? null,
                $data['provider_reference'] ?? null,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $exception;
            }
            report($exception);

            return $this->errorResponse($exception);
        }

        return response()->json([
            'message' => $data['decision'] === 'approved'
                ? 'Payment verified and the intended action completed.'
                : 'Payment marked as not verified.',
            'data' => $settled->load(['user:id,full_name,username,email']),
        ]);
    }

    public function balances(Request $request, GatewayBalances $balances): JsonResponse
    {
        abort_unless($this->role($request) === 'super_admin', 403, 'Only a super admin can view live gateway balances.');

        return response()->json($balances->fetch());
    }

    private function gatewayIntent(int $intent): PaymentIntent
    {
        return PaymentIntent::query()
            ->whereIn('provider', SettleGatewayPaymentManually::GATEWAY_PROVIDERS)
            ->findOrFail($intent);
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user();

        return $admin;
    }

    private function role(Request $request): string
    {
        return (string) ($request->user()?->role ?? '');
    }

    private function authorize(Request $request): void
    {
        abort_unless(in_array($this->role($request), self::ROLES, true), 403, 'A finance administrator account is required.');
    }

    private function errorResponse(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json(['message' => 'The payment action could not be completed.'], 500);
    }
}
