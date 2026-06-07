<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsOnboardingRequestSubmitted;
use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class PhsOnboardingController extends Controller
{
    public function showForm(Request $request)
    {
        $package = $request->query('package');
        return view('phs.onboarding-request-form', compact('package'));
    }

    public function confirmPayment(Request $request)
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'in:bank,law_firm,corporate'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'initial_token_package' => ['required', 'string', 'max:255'],
            'additional_notes' => ['nullable', 'string'],
        ]);

        // Store in session temporarily
        $request->session()->put('onboarding_data', $validated);

        // Calculate amount based on package
        $packagePrices = [
            'Starter' => 50000,
            'Professional' => 110000,
            'Enterprise' => 200000,
        ];

        $amount = $packagePrices[$validated['initial_token_package']] ?? 0;

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

        $this->notifyAdminRequestSubmitted($onboardingRequest);

        return redirect()->route('phs.request.pending', ['id' => $onboardingRequest->id])
            ->with('success', 'Request submitted successfully! Your application is now under review.');
    }

    public function showPending($id)
    {
        $request = PhsOnboardingRequest::findOrFail($id);
        return view('phs.request-pending', compact('request'));
    }

    private function notifyAdminRequestSubmitted(PhsOnboardingRequest $request)
    {
        $adminEmail = config('mail.admin_email') ?? config('mail.from.address');

        Mail::to($adminEmail)->send(new PhsOnboardingRequestSubmitted($request));
    }
}
