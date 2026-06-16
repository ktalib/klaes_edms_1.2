<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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
            subject: 'PHS Portal — Your Onboarding Link to Complete Registration',
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

    /**
     * Attach the "Disclaimer for PHS Portal and Online Legal Search" as a
     * downloadable PDF so every onboarded organization receives it.
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('phs.disclaimer-template', [
            'date' => now()->format('F j, Y'),
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'PHS-Disclaimer-and-Terms-of-Use.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
