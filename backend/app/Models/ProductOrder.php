<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One guest eSIM order from the product pages (product.n-calls.com).
 *
 * The lifecycle is deliberately tiny and only ever moves forward:
 *
 *   pending ──(gateway says paid, reconciled + claimed in the ledger)──► paid
 *      └────(gateway says failed / abandoned, or an underpayment)──────► failed
 *
 * `paid` is the money-truth (nothing else may set it); `fulfilled_at` is the human-truth — a staff
 * member has emailed the product. A paid order with no `fulfilled_at` is the fulfilment queue, which
 * is exactly what the backoffice list is built around.
 */
class ProductOrder extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    /** What kind of storefront item this order is for. */
    public const SOURCE_PACKAGE = 'package';

    public const SOURCE_PRODUCT = 'product';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:6',
            'amount_ngn' => 'decimal:2',
            'gateway_response' => 'array',
            'meta' => 'array',
            'paid_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'ops_notified_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    /** A unique order reference: ours, and the one the gateway charges and echoes back. */
    public static function generateReference(): string
    {
        do {
            $reference = 'PRD-'.strtoupper(str()->random(12));
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function paymentResolutionLogs(): HasMany
    {
        return $this->hasMany(PaymentResolutionLog::class, 'product_order_id')->latest();
    }

    public function paymentReference(): HasOne
    {
        return $this->hasOne(PaymentReference::class, 'reference', 'reference');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** An eSIM plan bought from the storefront (the item was sold as a package). */
    public function isPackage(): bool
    {
        return (string) $this->source === self::SOURCE_PACKAGE;
    }

    /** A catalog product an admin created in the backoffice. */
    public function isCatalogProduct(): bool
    {
        return (string) $this->source === self::SOURCE_PRODUCT;
    }

    /** "eSIM plan" / "Product" — what the buyer is receiving in plain words. */
    public function kindLabel(): string
    {
        return $this->isCatalogProduct() ? 'Product' : 'eSIM plan';
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled_at !== null;
    }

    /** Paid but not yet sent to the buyer — what staff have to act on. */
    public function awaitsFulfilment(): bool
    {
        return $this->isPaid() && ! $this->isFulfilled();
    }

    public function statusTone(): string
    {
        return match ((string) $this->status) {
            self::STATUS_PAID => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'warning',
        };
    }

    public function statusLabel(): string
    {
        return match ((string) $this->status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_FAILED => 'Failed',
            default => 'Awaiting payment',
        };
    }

    /**
     * The buyer's email with the middle hidden: the success page has to show WHICH address the
     * product goes to without turning a shared screenshot (or a leaked reference) into a full
     * address. `john.ok@gmail.com` → `jo•••@gmail.com`.
     */
    public function maskedEmail(): string
    {
        $email = (string) $this->email;
        $at = strrpos($email, '@');

        if ($at === false) {
            return $email;
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at);
        $keep = min(2, max(1, strlen($local) - 1));

        return substr($local, 0, $keep).str_repeat('•', max(1, strlen($local) - $keep)).$domain;
    }

    /** The amount as the pages show it. */
    public function formattedAmount(): string
    {
        return '₦'.number_format((float) $this->amount_ngn, 2);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeAwaitingFulfilment(Builder $query): Builder
    {
        return $query->paid()->whereNull('fulfilled_at');
    }
}
