<?php

namespace App\Mail;

use App\Models\LegalSearchOnlineRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers the approved Legal Search report to the requester as a PDF.
 */
class LegalSearchRequestApproved extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $report Payload from LegalSearchService::buildPrintReport().
     */
    public function __construct(
        public LegalSearchOnlineRequest $searchRequest,
        public array $report,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Legal Search Report — ' . ($this->searchRequest->file_number ?: $this->searchRequest->request_no),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.legal_search_request_approved',
            with: ['searchRequest' => $this->searchRequest],
        );
    }

    /**
     * The report itself, rendered landscape through the PDF-safe template.
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('online_legal_search.print.report_pdf', [
            'report'  => $this->report,
            'searchRequest' => $this->searchRequest,
        ])->setPaper('a4', 'landscape');

        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($this->searchRequest->file_number ?: 'report'));
        $slug = trim((string) $slug, '-') ?: 'report';

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Legal-Search-Report-' . $slug . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
