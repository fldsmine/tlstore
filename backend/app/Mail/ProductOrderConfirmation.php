<?php

namespace App\Mail;

use App\Models\ProductOrder;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The buyer's receipt for a guest eSIM order. Deliberately NOT the product itself: it confirms the
 * money arrived and says the eSIM is on its way to this same address, which is what the success page
 * promises.
 */
class ProductOrderConfirmation extends BaseMail
{
    public function __construct(public ProductOrder $order) {}

    public function envelope(): Envelope
    {
        $product = $this->order->product_name ?: 'your eSIM';

        return new Envelope(subject: 'Payment received — '.$product);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.product-order-confirmation',
            with: [
                'name' => $this->order->full_name,
                'product' => $this->order->product_name,
                'reference' => $this->order->reference,
                'amount' => $this->order->formattedAmount(),
                'email' => $this->order->email,
                'paidAt' => $this->order->paid_at?->format('j M Y, g:i A'),
                'support' => config('settings.contact.email'),
                'siteUrl' => config('product.main_url', config('app.url')),
            ],
        );
    }
}
