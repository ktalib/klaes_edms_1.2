{{-- Mandatory phone-number confirmation card.

     Two steps in one card: confirm (or correct) the number, then type back the code
     that was sent to it. RequirePhoneVerification enforces the same rule server-side,
     so closing this card by any means gains nothing — every other URL redirects
     straight back here.

     Deliberately its own design and its own CSS (css/phone-verification-card.css)
     rather than the photo card's shell, so changes to either never disturb the other.
     Rendered only while the account still owes a verified number. --}}
@php
    $pvcOtp = app(\App\Services\PhoneOtpService::class);
    $pvcUser = auth()->user();
    $pvcPhone = $pvcOtp->localFormat($pvcUser->phone_number ?? '');

    $pvcPrimaryLogo = asset('assets/logo/logo.png');
    $pvcSecondaryLogo = asset('assets/logo/Left_Logo.png');

    if (class_exists(\Illuminate\Support\Facades\Storage::class)) {
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('upload/logo/logo.png')) {
            $pvcPrimaryLogo = asset('storage/upload/logo/logo.png');
        }
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('uploads/logo.jpeg')) {
            $pvcSecondaryLogo = asset('storage/uploads/logo.jpeg');
        }
    }
@endphp

<link rel="stylesheet" href="{{ asset('css/phone-verification-card.css') }}">

<div id="phoneVerificationCard" class="pvc-overlay"
    data-send-url="{{ route('phone.verification.send') }}"
    data-verify-url="{{ route('phone.verification.verify') }}"
    data-phone="{{ $pvcPhone }}"
    data-code-length="{{ (int) config('phone_verification.code_length', 6) }}"
    data-cooldown="{{ $pvcOtp->cooldownRemaining($pvcUser) }}"
    role="dialog" aria-modal="true" aria-labelledby="pvcTitle" aria-hidden="true">

    <div class="pvc-card">
        <div class="pvc-masthead">
            <div class="pvc-logos">
                <img src="{{ $pvcPrimaryLogo }}" alt="KLAES">
                <span class="pvc-logo-rule" aria-hidden="true"></span>
                <img src="{{ $pvcSecondaryLogo }}" alt="Land Admin Enterprise System">
            </div>
            <p class="pvc-eyebrow">{{ __('Account setup') }}</p>
        </div>

        <div class="pvc-body">

            {{-- Step 1: the number itself. Prefilled with whatever is on the account,
                 and editable — the card exists because that value may be wrong, and a
                 user who cannot correct it here has no way through at all. --}}
            <div id="pvcStepPhone">
                <span class="pvc-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="2" width="12" height="20" rx="2" />
                        <line x1="11" y1="18" x2="13" y2="18" />
                    </svg>
                </span>

                <h2 class="pvc-title" id="pvcTitle">{{ __('Confirm Your Phone Number') }}</h2>

                <p class="pvc-lede">
                    {{ __('Hello') }} <strong>{{ $pvcUser->first_name ?? $pvcUser->name }}</strong>,
                    {{ __('KLAES sends sign-in alerts, file notices and other messages to your mobile number. Confirm the number below is yours — we will send a code to it.') }}
                </p>

                <div class="pvc-field">
                    <label class="pvc-label" for="pvcPhoneInput">{{ __('Mobile number') }}</label>
                    <input type="tel" id="pvcPhoneInput" class="pvc-input" inputmode="tel"
                        autocomplete="tel" value="{{ $pvcPhone }}" placeholder="08012345678">
                    <p class="pvc-hint">
                        @if ($pvcPhone === '')
                            {{ __('There is no number on your account yet. Enter the one you use, as 08012345678.') }}
                        @else
                            {{ __('If this is not your number, correct it before sending the code.') }}
                        @endif
                    </p>
                </div>

                <p id="pvcPhoneMessage" class="pvc-error pvc-hidden" role="alert"></p>

                <button type="button" id="pvcSendBtn" class="pvc-submit">
                    {{ __('Send Verification Code') }}
                </button>
            </div>

            {{-- Step 2: the code. --}}
            <div id="pvcStepCode" class="pvc-hidden">
                <span class="pvc-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16v12H5.17L4 17.17z" />
                        <line x1="8" y1="10" x2="8" y2="10" />
                        <line x1="12" y1="10" x2="12" y2="10" />
                        <line x1="16" y1="10" x2="16" y2="10" />
                    </svg>
                </span>

                <h2 class="pvc-title">{{ __('Enter The Code') }}</h2>

                <p class="pvc-lede">
                    {{ __('We sent a code to') }} <strong id="pvcMaskedPhone"></strong>.
                    {{ __('Type it below to finish setting up your account.') }}
                </p>

                <div class="pvc-field">
                    <label class="pvc-label" for="pvcCodeInput">{{ __('Verification code') }}</label>
                    <input type="text" id="pvcCodeInput" class="pvc-input pvc-input-code"
                        inputmode="numeric" autocomplete="one-time-code"
                        maxlength="{{ (int) config('phone_verification.code_length', 6) }}"
                        placeholder="{{ str_repeat('0', (int) config('phone_verification.code_length', 6)) }}">
                    <p class="pvc-hint">
                        {{ __('The code expires in :minutes minutes.', ['minutes' => $pvcOtp->ttlMinutes()]) }}
                    </p>
                </div>

                <p id="pvcCodeMessage" class="pvc-error pvc-hidden" role="alert"></p>

                <button type="button" id="pvcVerifyBtn" class="pvc-submit">
                    {{ __('Verify My Number') }}
                </button>

                <div class="pvc-actions">
                    <button type="button" id="pvcBackBtn" class="pvc-link">{{ __('Use a different number') }}</button>
                    <button type="button" id="pvcResendBtn" class="pvc-link">{{ __('Resend code') }}</button>
                </div>

                {{-- The one thing a member of staff cannot work out for themselves: the
                     network holds these messages overnight, so a code asked for at 21:00
                     genuinely will not arrive until the morning. Saying so here saves the
                     call to ICT. --}}
                <p class="pvc-note">
                    <strong>{{ __('Not arriving?') }}</strong>
                    {{ __('Nigerian networks hold these messages between 7:45pm and 8:00am — a code requested at night arrives from about 8:30am. If your line blocks bulk SMS, contact ICT and they will confirm your number for you.') }}
                </p>
            </div>

            <p class="pvc-footnote">
                {{ __('The system stays locked until your phone number is confirmed.') }}
            </p>
        </div>
    </div>
</div>

<script src="{{ asset('js/phone-verification-card.js') }}?v={{ filemtime(public_path('js/phone-verification-card.js')) }}" defer></script>
