<?php

namespace App\Mail;

use App\Models\ProductOrder;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The internal nudge that a guest order has been PAID: someone has to send the product to the buyer,
 * and this is what makes that queue visible outside the backoffice.
 */
class ProductOrderNotice extends BaseMail
{
    public function __construct(public ProductOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Nextel] Paid eSIM order — '.$this->order->reference.' ('.$this->order->product_name.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.product-order-notice',
            with: [
                'product' => $this->order->product_name,
                'reference' => $this->order->reference,
                'amount' => $this->order->formattedAmount(),
                'buyerName' => $this->order->full_name,
                'buyerPhone' => $this->order->phone,
                'buyerEmail' => $this->order->email,
                'paidAt' => $this->order->paid_at?->format('j M Y, g:i A'),
                'provider' => $this->order->provider,
                'orderUrl' => route('backoffice.products.orders.show', ['order' => $this->order->id]),
            ],
        );
    }
}
