{{--
    Online Legal Search — Identify your Customer (IYC) card.

    Sits beside the payment card on desktop and stacks below it on smaller
    screens. Submitting it runs ID NAME verification on the server: the name
    typed here is compared with the text OCR reads off the uploaded document.

    That check confirms the NAME MATCHES. It is not a check that the document is
    genuine, unaltered, or presented by its rightful holder — the wording on this
    card is deliberately limited to what is actually verified.

    Every rule below is re-applied server-side (StoreIdVerificationRequest); the
    JavaScript here only saves the applicant a round trip.
--}}
@php
    $idTypes = config('id_verification.types', []);
@endphp

<div class="pay-wrap" id="idCard">
    <div class="pay-head">
        <h1>Identify your Customer (IYC)</h1>
        <p>We check that the name you enter matches the name on your ID.</p>
    </div>
    <div class="pay-body">

        <label for="idFullName" class="id-label">Full name <span style="color:#dc2626;">*</span></label>
        <input id="idFullName" type="text" class="id-input" autocomplete="name"
               placeholder="As written on your identification" maxlength="200" />

        <label for="idAddress" class="id-label">Residential / contact address <span style="color:#dc2626;">*</span></label>
        <textarea id="idAddress" class="id-input" rows="2" autocomplete="street-address"
                  placeholder="House number, street, town" maxlength="500"></textarea>

        <label for="idType" class="id-label">Means of identification <span style="color:#dc2626;">*</span></label>
        <select id="idType" class="id-input">
            <option value="">— Select your ID —</option>
            @foreach($idTypes as $key => $type)
                <option value="{{ $key }}"
                        data-front-label="{{ $type['front_label'] ?? 'Photo of your ID' }}">
                    {{ $type['label'] }}
                </option>
            @endforeach
        </select>

        {{-- Only for "Other government-issued ID", where the label is required. --}}
        <div id="idTypeOtherWrap" style="display:none;">
            <label for="idTypeOther" class="id-label">Identification type <span style="color:#dc2626;">*</span></label>
            <input id="idTypeOther" type="text" class="id-input"
                   placeholder="e.g. NYSC Discharge Certificate" maxlength="120" />
        </div>

        {{-- One image only: the side carrying the name. Every accepted document
             prints it on the front, so a second upload added a step without
             adding anything to the comparison. --}}
        <div class="id-upload">
            <label for="idFront" class="id-label" id="idFrontLabel">Photo of your ID <span style="color:#dc2626;">*</span></label>
            <div class="id-preview id-preview-wide" id="idFrontPreview"><span class="id-preview-empty">No image selected</span></div>
            <input id="idFront" type="file" accept="image/jpeg,image/png,image/webp" class="id-file" />
            <button type="button" class="id-clear" id="idFrontClear" style="display:none;">Replace image</button>
        </div>

        <p class="pay-note" style="text-align:left;margin-top:10px;">
            JPEG, PNG or WebP, up to 5MB. Show the side with your name on it. Your document is
            stored privately and is only visible to the approving officer.
        </p>

        {{-- No button: the check runs on its own once every field above is filled
             and an image is chosen, and again whenever one of them changes. --}}
        <div id="idStatus" class="id-status" style="display:none;"></div>

        <p class="pay-note" style="text-align:left;">
            This step confirms the name you entered matches the name printed on your ID.
            It is not a check of the document's authenticity.
        </p>
    </div>
</div>
