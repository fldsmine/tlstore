# Fincra integration

Fincra is wired in as a first-class hosted-checkout gateway alongside `korapay`,
`paystack`, `flutterwave` and `bachs`: wallet top-ups, subscription checkouts and
package-add-on checkouts, plus webhook settlement, revalidation, the admin
gateway-payment console, live balances and payout-account resolution.

Source of truth for everything below: <https://docs.fincra.com/docs/getting-started>
(the docs expose `.md` versions of every page, and an index at
<https://docs.fincra.com/llms.txt>).

## What is wired

| Concern | Code | Fincra call |
|---|---|---|
| Create checkout | `InitializeGatewayTopup` | `POST /checkout/payments` |
| Webhook | `ProcessGatewayWebhook` | `charge.successful` → verify with the provider |
| Revalidate / "check payment" | `VerifyGatewayTopup` | `GET /checkout/payments/merchant-reference/{reference}` |
| Live balances | `GatewayBalances` | `GET /wallets?businessID=…` |
| Payout account name | `ResolvePayoutAccount` | `POST /core/accounts/resolve` |
| Manual settlement / revalidation console | `SettleGatewayPaymentManually`, `RevalidateGatewayPayment` | reuses the two calls above |

Shared plumbing (auth headers, base URL, major-unit conversion) lives in
`App\Application\Payments\FincraApi`.

## Configuration

```dotenv
FINCRA_SECRET_KEY=            # Portal → Settings → API keys (secret)
FINCRA_PUBLIC_KEY=            # Portal → Settings → API keys (public, `x-pub-key`)
FINCRA_BUSINESS_ID=           # Parent business ID; required for /wallets
FINCRA_WEBHOOK_SECRET=        # Portal → Settings → Secret keys (webhook signing)
FINCRA_BASE_URL=https://api.fincra.com
FINCRA_FEE_BEARER=business    # `business` (default) or `customer`
```

Sandbox: `FINCRA_BASE_URL=https://sandboxapi.fincra.com` (checkout links come back
on `sandbox-checkout.fincra.com`).

`fincra` only appears in `trustlens.implemented_payment_gateways` when **both**
`FINCRA_SECRET_KEY` and `FINCRA_WEBHOOK_SECRET` are set — the same rule as
Flutterwave and Bachs. After setting them run `php artisan config:cache`.

Then, in the admin console (Flutter *Payment gateways* screen or
`PUT /api/v1/admin/settings/payment.active_gateways`), add `fincra` with the
currencies you want to expose and add `gateway.CURRENCY` labels under
`payment.active_payment_currency`. Default currency set:
`NGN, GHS, KES, UGX, TZS, ZMW, USD, XAF, XOF`.

Register the webhook in the Fincra portal:

```
https://<PAYMENT_WEBHOOK_BASE_URL>/api/v1/webhooks/fincra
```

## Mechanics that matter

- **Auth**: `api-key: <secret>` (+ optional `x-pub-key`, `x-business-id`), **not**
  a bearer token. `FincraApi::client()` centralises this.
- **Amounts are major units** (`1500` = NGN 1,500.00), unlike Paystack's kobo
  integers. `FincraApi::amount()` converts from the intent's minor amount and
  keeps whole amounts as integers; `CurrencyMinorUnits` handles zero-decimal
  currencies (XAF/XOF) and the 2-decimal ones.
- **References**: we generate `tls-<user>-<random>` and store it as
  `payment_intents.provider_reference`. The checkout response's `payCode`
  (`fcr-p-…`) and `reference` are kept in `provider_metadata`
  (`fincra_pay_code`, `fincra_reference`). Webhooks match on
  `data.merchantReference` first, then fall back to the stored pay code, because
  some deliveries only carry Fincra's own reference.
- **Signature**: `signature: <hex HMAC-SHA512 of the raw request body>` keyed
  with the webhook secret. The handler hashes `$request->getContent()` with
  `hash_equals`; **never** re-encode the decoded JSON — key order and escapes
  change and the digest will not match. Fincra's own docs warn the payload must
  arrive "encrypted and unmodified".
- **Only success events**: Fincra sends webhooks for successful charges only, so
  abandoned/failed checkouts are closed by the intent expiry job or by a
  verification call, never by a webhook. A replayed delivery is deduplicated on
  `data.id` (`payment_webhook_events`).
- **Under/overpayments**: bank transfers can land for a different amount. The
  verify response exposes `amountReceived` and `varianceType`
  (`underpayment` / `overpayment`). Settlement compares `amountReceived` against
  the intent, so a partial payment **fails the webhook** (event recorded as
  `failed`, intent left pending) instead of crediting more than the customer
  paid. Finance resolves it from the admin *Gateway payments* console
  (settle by hand) or refunds in the Fincra portal — Fincra's refund API is not
  automated here.
- **Fees**: `feeBearer: business` (configurable) so the payer pays exactly the
  quoted amount and fees are netted in the Fincra wallet; the ledger credits the
  intent amount, as with every other gateway.
- **Balances**: `/wallets` reports `availableBalance` in major units and needs
  the `businessID` query parameter. The admin page shows the configured
  currencies and never 500s on a provider error.
- **Payout account resolution**: uses NUBAN bank codes (`banks.code`), i.e. the
  CBN code, with `currency` derived from the bank's country. Set
  `account_validation.provider` to `fincra` to use it for withdrawal-bank
  validation.

## Currencies

Checkout accepts NGN, GHS, KES, UGX, TZS, ZMW, USD, XAF and XOF. NGN supports
card, bank transfer, USSD, OPay and PayAttitude; other markets vary (mobile
money, bank transfer). ZAR is deliberately **not** enabled by default: Fincra's
checkout reference does not list it among accepted checkout currencies even
though payouts support ZAR — confirm with Fincra before enabling it in
`payment.active_gateways`.

## Tests

```
.tools/bin/php vendor/phpunit/phpunit/phpunit tests/Feature/FincraGatewayTest.php
```

Covers checkout creation (headers, major-unit amount, stored pay code), a signed
webhook crediting the wallet exactly once, invalid signatures (401), underpayment
(no credit, failed event), pay-code-only payloads, the `POST /topups/{id}/check`
path, wallet balances, payout resolution and the admin settings allowlists for
`fincra`.

## Operations checklist

1. Set the four Fincra environment variables; `php artisan config:cache`.
2. Add the webhook URL + secret in the Fincra portal (use the same secret as
   `FINCRA_WEBHOOK_SECRET`).
3. Enable `fincra` and its currencies in admin payment settings; give each
   currency a customer-facing label.
4. Run one sandbox checkout end to end (card and bank transfer), confirm the
   webhook is processed (`payment_webhook_events.processing_status = processed`)
   and the wallet is credited.
5. Check *Live gateway balances* renders the NGN/GHS wallet rows.
