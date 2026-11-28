<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Application\Payments\GatewayBalances;
use App\Application\Payments\RevalidateGatewayPayment;
use App\Application\Payments\SettleGatewayPaymentManually;
use App\Models\Admin;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Admin console: gateway deposits/top-ups.
 *
 * Everything a provider charged but the platform has not settled yet lands
 * here, so finance can revalidate it with the provider or settle it by hand.
 */
final class GatewayPaymentAdminController
{
    /** Roles allowed to work on money. */
    public const ROLES = ['super_admin', 'admin', 'finance'];

    public function index(Request $request): View
    {
        $this->authorize();

        $filters = $request->validate([
            'provider' => ['nullable', Rule::in(SettleGatewayPaymentManually::GATEWAY_PROVIDERS)],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'cancelled', 'expired', 'refunded'])],
            'purpose' => ['nullable', Rule::in(['topup', 'subscription', 'package_addon'])],
            'q' => ['nullable', 'string', 'max:120'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $query = PaymentIntent::query()
            ->with(['user:id,full_name,username,email'])
            ->whereIn('provider', SettleGatewayPaymentManually::GATEWAY_PROVIDERS);

        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }
        if (! empty($filters['q'])) {
            $needle = '%'.trim($filters['q']).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->where('provider_reference', 'like', $needle)
                    ->orWhere('idempotency_key', 'like', $needle)
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', $needle)
                        ->orWhere('username', 'like', $needle)
                        ->orWhere('full_name', 'like', $needle));
            });
        }
        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date'].' 00:00:00');
        }
        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date'].' 23:59:59');
        }

        $summary = [
            'awaiting' => (clone $query)->where('status', 'pending')->count(),
            'paid' => (clone $query)->where('status', 'paid')->count(),
            'closed' => (clone $query)->whereIn('status', ['failed', 'cancelled', 'expired'])->count(),
        ];

        return view('admin.gateway-payments.index', [
            'title' => 'Gateway payments',
            'intents' => $query->latest('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function show(int $intent): View
    {
        $this->authorize();

        $paymentIntent = PaymentIntent::query()
            ->with(['user:id,full_name,username,email,wallet_currency', 'transactions'])
            ->whereIn('provider', SettleGatewayPaymentManually::GATEWAY_PROVIDERS)
            ->findOrFail($intent);

        return view('admin.gateway-payments.show', [
            'title' => 'Gateway payment #'.$paymentIntent->id,
            'intent' => $paymentIntent,
            'transaction' => Transaction::query()
                ->where('reference', 'payment-intent:'.$paymentIntent->id)->first(),
            'paymentTransactions' => PaymentTransaction::query()
                ->where('payment_intent_id', $paymentIntent->id)->latest('id')->get(),
            'ledgerEntries' => DB::table('ledger_entries')
                ->where('source_type', 'payment_intent')->where('source_id', $paymentIntent->id)
                ->orderBy('id')->get(),
        ]);
    }

    public function revalidate(int $intent, RevalidateGatewayPayment $revalidate): RedirectResponse
    {
        $this->authorize();
        $paymentIntent = $this->gatewayIntent($intent);

        try {
            $result = $revalidate->handle($this->admin(), $paymentIntent);
        } catch (Throwable $exception) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $exception;
            }
            report($exception);

            return back()->withErrors(['payment' => $exception instanceof ValidationException
                ? $exception->getMessage()
                : 'The payment could not be revalidated.']);
        }

        return back()->with($result['outcome'] === 'error' ? 'error' : 'success', $result['message']);
    }

    public function decide(Request $request, int $intent, SettleGatewayPaymentManually $settle): RedirectResponse
    {
        $this->authorize();

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'provider_reference' => ['nullable', 'string', 'max:191'],
        ]);

        $paymentIntent = $this->gatewayIntent($intent);

        try {
            $settled = $settle->handle(
                $this->admin(),
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

            return back()->withErrors(['payment' => $exception instanceof ValidationException
                ? $exception->getMessage()
                : 'The payment decision could not be saved.']);
        }

        return back()->with(
            'success',
            $data['decision'] === 'approved'
                ? 'Payment verified and the intended action completed (#'.$settled->id.').'
                : 'Payment marked as not verified (#'.$settled->id.').',
        );
    }

    public function balances(GatewayBalances $balances): View
    {
        $this->authorizeSuperAdmin();

        return view('admin.gateway-payments.balances', [
            'title' => 'Gateway balances',
            'snapshot' => $balances->fetch(),
        ]);
    }

    private function gatewayIntent(int $intent): PaymentIntent
    {
        return PaymentIntent::query()
            ->whereIn('provider', SettleGatewayPaymentManually::GATEWAY_PROVIDERS)
            ->findOrFail($intent);
    }

    private function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }

    private function authorize(): void
    {
        abort_unless(in_array((string) $this->admin()->role, self::ROLES, true), 403);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless((string) $this->admin()->role === 'super_admin', 403);
    }
}
