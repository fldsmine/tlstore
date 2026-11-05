<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentOption extends Model
{
    protected $fillable = [
        'name', 'currency', 'symbol', 'method_code', 'gateway_alias',
        'percent_charge', 'fixed_charge', 'rate', 'status', 'description',
        'addresses', 'logo', 'order',
    ];

    protected $casts = [
        'percent_charge' => 'float',
        'fixed_charge' => 'float',
        'rate' => 'float',
        'status' => 'boolean',
        'addresses' => 'array',
    ];


    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function calculatePayable($usdAmount)
    {
        $baseAmount = $usdAmount * $this->rate;
        $percentCharge = ($baseAmount * $this->percent_charge) / 100;
        $totalCharge = $percentCharge + $this->fixed_charge;
        return round($baseAmount + $totalCharge, 2);
    }
}
