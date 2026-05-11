<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Consent to Assign (GIFT) -{{ $application->file_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 1.2cm 1.5cm 1.5cm 1.5cm;
        }

        body {
            font-family: 'Times New Roman';
            font-size: 13pt;
            line-height: 1.3;
            color: #000;
            background-color: white !important;
        }

        .json-data {
            font-weight: bold;
        }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #006633;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(200, 200, 200, 0.25);
            font-weight: bold;
            text-transform: uppercase;
            z-index: -1;
            pointer-events: none;
            white-space: nowrap;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }
        }

        .letterhead-space {
            min-height: 240px;
            width: 100%;
            margin-bottom: 10px;
            position: relative;
        }

        .letterhead-space::before {
            content: "[Space for pre-printed letterhead]";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #999;
            font-style: italic;
            font-size: 14px;
            text-align: center;
            border: 1px dashed #ccc;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            width: 80%;
        }

        @media print {
            .letterhead-space::before {
                display: none !important;
            }
        }

        .footer-logos {
            position: absolute;
            bottom: 1.0cm;
            left: 1.5cm;
            right: 1.5cm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
        }

        .footer-logo-img {
            height: 35px;
            width: auto;
            object-fit: contain;
        }

        .logo-left {
            height: 45px; /* Slightly larger to match visual weight */
        }
    </style>
</head>

<body class="print:bg-white">

    @php
        $watermarkText = $application->print_count == 0 ? 'FIRST PRINT' : 'SECOND PRINT';
    @endphp
    <div class="watermark">{{ $watermarkText }}</div>

    <button class="print-btn no-print" id="print-action">
        <i class="fas fa-print"></i> Print Document
    </button>

    <div class="w-[210mm] mx-auto bg-white p-[1.5cm] box-border relative">
        <div class="letterhead-space"></div>

        <div class="text-center font-bold pt-2">{{ $application->file_number }}</div>

        <div class="text-right mt-6 text-sm">
            <strong>{{ $application->created_at->format('jS F, Y') }}</strong>
        </div>

        <div class="mt-6 leading-relaxed text-sm">
            <span class="json-data">{{ strtoupper($application->applicant_name) }}</span><br>
            <span
                class="json-data">{!! preg_replace('/, ([^,]+ State)$/i', ',<br>$1.', e(strtoupper($application->applicant_address))) !!}</span>
        </div>

        <div class="mt-4 text-sm">Sir,</div>

        <div class="text-center mt-6 uppercase font-bold text-base leading-tight">
            RE: APPLICATION FOR CONSENT TO ASSIGN (GIFT) THE PROPERTY COVERED BY<br>
            CERTIFICATE OF OCCUPANCY: <span class="json-data">{{ $application->file_number }}</span>
        </div>

        <div class="mt-6 leading-relaxed text-md text-justify">
            By virtue of the powers conferred upon the Governor of Kano state by the provisions 9, 21 and 22 of the Land
            Act Laws of the Federation of Nigeria, Vol.118, and further to your application dated <span
                class="json-data">{{ \Carbon\Carbon::parse($application->application_date)->format('jS F, Y') }}</span>
            the above subject matter. I hereby Convey my Approval for Consent to Assign the property with Certificate of
            Occupancy No.<span class="json-data">{{ $application->file_number }}</span> To <span
                class="json-data">{{ strtoupper($application->party_name) }}</span> of <span
                class="json-data">{{ rtrim($application->party_address, '.') }}.</span> <span
                class="json-data">{{ $application->consideration_words }}</span> this is subject to the submission
            of a deed of Assignment and payment of Stamp Duty.
        </div>

        <div class="mt-8 text-sm">Yours faithfully,</div>

        <div class="mt-12">
            <div class="font-bold uppercase text-base json-data">ALH ABDULJABBAR M. UMAR</div>
            <div class="font-bold uppercase text-sm">HON. COMMISSIONER,</div>
            <div class="uppercase text-sm">MINISTRY OF LAND AND PHYSICAL PLANNING,</div>
            <div class="uppercase text-sm">KANO STATE</div>

            <div class="mt-6 pt-4 border-t border-gray-300 text-xs text-gray-600">
                <!-- <div>OFFICIAL STAMP:</div>
                <div class="w-32 h-12 border border-gray-400 mt-1 flex items-center justify-center text-gray-500">
                    [Official Stamp Here]
                </div> -->
            </div>
        </div>

        <div class="footer-logos">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" class="footer-logo-img logo-left" alt="Left Logo">
            <img src="http://app.klaes.ng/assets/logo/las.jpg" class="footer-logo-img" alt="Right Logo">
        </div>
    </div>

    <script>
        document.getElementById('print-action').addEventListener('click', async function () {
            try {
                const response = await fetch('{{ route("consent-applications.log-print", $application->id) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (result.success) {
                    window.print();
                    setTimeout(() => { window.location.reload(); }, 500);
                } else {
                    alert('Error logging print: ' + result.message);
                }
            } catch (error) {
                console.error('Print logging error:', error);
                alert('An error occurred while preparing for print.');
            }
        });
    </script>
</body>

</html>