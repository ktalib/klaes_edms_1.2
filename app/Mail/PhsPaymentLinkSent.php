<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsPaymentLinkSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PHS Portal — Your Request Has Been Approved. Please Activate Your Account.',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_payment_link',
            with: [
                'request' => $this->request,
                'paymentUrl' => route('phs.payment.form', [$this->request->id, $this->request->payment_token]),
            ],
        );
    }
}
