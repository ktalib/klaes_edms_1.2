<?php

namespace App\Mail;

use App\Models\LegalSearchOnlineRequest;
use App\Services\LegalSearchApprovalService;
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
        // Rendered through the service so the attachment and the approver's
        // preview are produced by exactly the same code path.
        $service = app(LegalSearchApprovalService::class);
        $pdf     = $service->renderPdf($this->searchRequest, $this->report);

        return [
            Attachment::fromData(fn () => $pdf->output(), $service->pdfFileName($this->searchRequest))
                ->withMime('application/pdf'),
        ];
    }
}
