<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AdditionalMinutePlan;
use App\Models\CloudStoragePlan;
use App\Models\CouponBatch;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public coupon lookup payload for the mobile verification screen.
 *
 * @mixin \App\Models\Coupon
 */
class CouponVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $valid = (bool) $this->is_valid;
        $status = $this->status_label;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type?->value,
            'type_name' => $this->type?->getName(),
            'price' => $this->price !== null ? (string) $this->price : null,
            'region' => $this->region,
            'is_special' => (bool) $this->is_special,
            'is_valid' => $valid,
            'status' => $status,
            'used_at' => $this->used_at?->toIso8601String(),
            'display_name' => $this->displayName(),
            'package' => $this->product($this->package),
            'additional_minute_plan' => $this->product($this->additionalMinutePlan),
            'cloud_storage_plan' => $this->product($this->cloudStoragePlan),
            'batch' => $this->batchPayload($this->batch),
            'agent' => $this->person($this->agent, includeId: true),
            'redeemer' => $this->when(
                $this->user_id !== null,
                fn (): ?array => $this->person($this->redeemer, includeId: false),
            ),
            'verification' => [
                'is_valid' => $valid,
                'status' => $status,
                'message' => $valid
                    ? 'This coupon is valid and has not been redeemed.'
                    : 'This coupon has already been redeemed.',
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function product(mixed $model): ?array
    {
        if (! $model) {
            return null;
        }

        $payload = [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'price' => null,
            'duration' => null,
            'minutes' => null,
            'storage' => null,
            'storage_size' => null,
        ];

        if ($model instanceof Package) {
            $payload['price'] = (string) $model->price;
            $payload['minutes'] = $model->daily_call_minutes !== null
                ? (float) $model->daily_call_minutes
                : null;
        } elseif ($model instanceof AdditionalMinutePlan) {
            $payload['price'] = (string) $model->price;
            $payload['duration'] = (int) $model->duration_days;
            $payload['minutes'] = $model->daily_call_minutes !== null
                ? (float) $model->daily_call_minutes
                : null;
        } elseif ($model instanceof CloudStoragePlan) {
            $payload['price'] = (string) $model->token_price;
            $payload['duration'] = (int) $model->duration_days;
            $payload['storage'] = (int) $model->extra_contacts;
            $payload['storage_size'] = (int) $model->extra_contacts;
            $payload['extra_sms'] = (int) $model->extra_sms;
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function batchPayload(?CouponBatch $batch): ?array
    {
        if (! $batch) {
            return null;
        }

        return [
            'id' => $batch->id,
            'reference' => $batch->reference,
            'type' => $batch->type?->value,
            'type_name' => $batch->type?->getName(),
            'region' => $batch->region,
            'display_name' => $batch->displayName(),
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }

    /** Public vendor / redeemer fields only — never email, phone, or balances. */
    private function person(?User $user, bool $includeId): ?array
    {
        if (! $user) {
            return null;
        }

        $data = [
            'name' => $user->full_name,
            'username' => $user->username,
        ];

        if ($includeId) {
            $data = ['id' => $user->id] + $data;
        }

        return $data;
    }
}
