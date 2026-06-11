<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsInvoiceIssued;
use App\Mail\PhsOnboardingRequestSubmitted;
use App\Models\Phs\PhsOnboardingRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PhsOnboardingController extends Controller
{
    public function showForm(Request $request)
    {
        $package = $request->query('package');
        $packages = PhsTokenController::packages();
        return view('phs.onboarding-request-form', compact('package', 'packages'));
    }

    public function confirmPayment(Request $request)
    {
        $packages = PhsTokenController::packages();
        $packageNames = array_column($packages, 'name');

        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'in:bank,law_firm,corporate'],
            'contact_first_name' => ['required', 'string', 'max:255'],
            'contact_last_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_email_confirmation' => ['required', 'email', 'same:contact_email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'initial_token_package' => ['required', 'string', Rule::in($packageNames)],
            'additional_notes' => ['nullable', 'string'],
            'cac_registration_number' => ['required', 'string', 'max:100'],
            'cac_document' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'additional_documents' => ['nullable', 'array'],
            'additional_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Compose a single contact_name for display/storage
        $validated['contact_name'] = trim(($validated['contact_first_name'] ?? '') . ' ' . ($validated['contact_last_name'] ?? ''));

        // Files can't live in the session — store them now and keep their paths.
        $validated['cac_document_path'] = $request->file('cac_document')->store('phs/cac-documents', 'public');

        $additional = [];
        foreach ((array) $request->file('additional_documents', []) as $file) {
            $additional[] = $file->store('phs/additional-documents', 'public');
        }
        $validated['additional_documents'] = $additional; // array — model casts to JSON on save

        // Store request data in session temporarily, dropping the confirmation
        // field and the raw uploaded file (only its stored path is kept).
        $request->session()->put(
            'onboarding_data',
            array_diff_key($validated, ['contact_email_confirmation' => true, 'cac_document' => true])
        );

        // Price comes from the single source of truth (PhsTokenController::packages()).
        $package = $packages[strtolower($validated['initial_token_package'])] ?? null;
        $amount = $package['price'] ?? 0;

        return view('phs.onboarding-payment', compact('validated', 'amount'));
    }

    public function submitRequest(Request $request)
    {
        $validated = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['required', 'string', 'max:255'],
        ]);

        $onboardingData = $request->session()->get('onboarding_data');

        if (!$onboardingData) {
            return redirect()->route('phs.request.form')
                ->withErrors(['error' => 'Session expired. Please start again.']);
        }

        // Check email not already used
        $emailExists = \DB::connection('sqlsrv')->table('phs_institutions')
            ->where('email', $onboardingData['contact_email'])
            ->exists();

        if ($emailExists) {
            return back()
                ->withErrors(['contact_email' => 'This email is already registered.']);
        }

        $emailExists = \DB::connection('sqlsrv')->table('phs_members')
            ->where('email', $onboardingData['contact_email'])
            ->exists();

        if ($emailExists) {
            return back()
                ->withErrors(['contact_email' => 'This email is already registered.']);
        }

        $onboardingRequest = PhsOnboardingRequest::create([
            ...$onboardingData,
            ...$validated,
            'status' => PhsOnboardingRequest::STATUS_PAYMENT_RECEIVED,
            'payment_received_at' => now(),
        ]);

        $request->session()->forget('onboarding_data');

        // Generate the e-invoice PDF before notifying admins so it can be attached.
        $this->generateInvoice($onboardingRequest);

        $this->notifyAdminRequestSubmitted($onboardingRequest);

        // Email the organization their invoice (best-effort — never block submission).
        try {
            Mail::to($onboardingRequest->contact_email)->send(new PhsInvoiceIssued($onboardingRequest));
            $onboardingRequest->update(['invoice_sent_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('phs.request.pending', ['id' => $onboardingRequest->id])
            ->with('success', 'Request submitted successfully! Your application is now under review.');
    }

    public function showPending($id)
    {
        $request = PhsOnboardingRequest::findOrFail($id);
        return view('phs.request-pending', compact('request'));
    }

    /**
     * Build, store, and stamp the e-invoice PDF for an onboarding request.
     * Idempotent: reuses the existing invoice number once generated.
     */
    public function generateInvoice(PhsOnboardingRequest $onboardingRequest): PhsOnboardingRequest
    {
        $packages = PhsTokenController::packages();
        $package = $packages[strtolower((string) $onboardingRequest->initial_token_package)] ?? null;
        $amount = $onboardingRequest->payment_amount ?? ($package['price'] ?? 0);

        $invoiceNumber = $onboardingRequest->invoice_number ?: $onboardingRequest->generateInvoiceNumber();

        // QR code encoding the organization reference id (payment reference,
        // falling back to the invoice number) so the invoice can be scanned/verified.
        $qrContent = $onboardingRequest->payment_reference ?: $invoiceNumber;
        $qrCode = $this->buildQrDataUri($qrContent);

        $pdf = Pdf::loadView('phs.invoice-template', [
            'request' => $onboardingRequest,
            'package' => $package,
            'amount' => (float) $amount,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now(),
            'qr_code' => $qrCode,
            'qr_content' => $qrContent,
        ]);

        $path = 'phs/invoices/' . $invoiceNumber . '.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $onboardingRequest->update([
            'invoice_number' => $invoiceNumber,
            'invoice_pdf_path' => $path,
            'invoice_generated_at' => now(),
        ]);

        return $onboardingRequest;
    }

    /**
     * Render a QR code for the given content as an SVG data URI (embeddable in
     * the DomPDF invoice). Returns null if QR generation fails for any reason.
     */
    private function buildQrDataUri(string $content): ?string
    {
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(140, 1),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $svg = (new \BaconQrCode\Writer($renderer))->writeString($content);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Download the invoice for a just-submitted onboarding request (pending page). */
    public function downloadInvoice($id)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);

        if (!$onboardingRequest->invoice_pdf_path) {
            $this->generateInvoice($onboardingRequest);
            $onboardingRequest->refresh();
        }

        return $this->streamInvoice($onboardingRequest);
    }

    /** Download the invoice for the signed-in organization. */
    public function downloadOrgInvoice()
    {
        $institution = Auth::guard('phs')->user()->institution;

        $onboardingRequest = PhsOnboardingRequest::where('created_phs_institution_id', $institution->id)
            ->orderByDesc('id')
            ->first();

        if (!$onboardingRequest) {
            abort(404, 'No invoice is available for your organization.');
        }

        if (!$onboardingRequest->invoice_pdf_path) {
            $this->generateInvoice($onboardingRequest);
            $onboardingRequest->refresh();
        }

        return $this->streamInvoice($onboardingRequest);
    }

    private function streamInvoice(PhsOnboardingRequest $onboardingRequest)
    {
        $filename = 'Invoice-' . ($onboardingRequest->invoice_number ?: $onboardingRequest->id) . '.pdf';

        if ($onboardingRequest->invoice_pdf_path && Storage::disk('public')->exists($onboardingRequest->invoice_pdf_path)) {
            return Storage::disk('public')->download($onboardingRequest->invoice_pdf_path, $filename);
        }

        abort(404, 'Invoice file not found.');
    }

    private function notifyAdminRequestSubmitted(PhsOnboardingRequest $request)
    {
        $adminEmail = config('mail.admin_email') ?? config('mail.from.address');

        // Support a comma-separated list of admin recipients.
        $recipients = collect(explode(',', (string) $adminEmail))
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values()
            ->all();

        Mail::to($recipients)->send(new PhsOnboardingRequestSubmitted($request));
    }
}
