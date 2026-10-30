<?php

namespace App\Mail;

use App\Models\ProductOrder;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The product itself, sent to the buyer by a staff member from the backoffice: whatever they paste
 * into the delivery box lands here verbatim, so the delivery path can change (a code, an account
 * detail, a link) without a code change.
 */
class ProductDelivery extends BaseMail
{
    public function __construct(
        public ProductOrder $order,
        public string $details,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.($this->order->product_name ?: 'eSIM').' — Nextel');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.product-delivery',
            with: [
                'name' => $this->order->full_name,
                'product' => $this->order->product_name,
                'reference' => $this->order->reference,
                'details' => $this->details,
                'note' => $this->note,
                'support' => config('settings.contact.email'),
                'siteUrl' => config('product.main_url', config('app.url')),
            ],
        );
    }
}
