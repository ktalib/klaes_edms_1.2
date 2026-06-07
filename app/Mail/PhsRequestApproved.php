<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsRequestApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PHS Onboarding Request Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_request_approved',
            with: [
                'request' => $this->request,
                'registrationUrl' => route('phs.register.token', ['token' => $this->request->activation_token]),
                'expiresAt' => $this->request->activation_token_expires_at,
            ],
        );
    }
}
