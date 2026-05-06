<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLTR Recommendation - {{ $recommendation->sltr_number ?? 'N/A' }}</title>
    <style>
        :root {
            --primary-green: #2e7d32;
            --accent-red: #a00000;
        }

        /* Basic Reset and Layout */
        * { box-sizing: border-box; }
        body {
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12.5pt; /* Slightly smaller to ensure fit */
        }

        .a4-page {
            background: white;
            width: 210mm;
            height: 297mm; /* Forced height to ensure single page */
            margin: 10px auto;
            display: flex;
            overflow: hidden; /* Prevents text from spilling out */
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        /* Side Margin Placeholder */
        .side-placeholder {
            width: 50px;
            /* border-right: 2px solid var(--primary-green); */
            flex-shrink: 0;
        }

        .main-container {
            flex: 1;
            padding: 15px 35px; /* Reduced padding to save space */
            display: flex;
            flex-direction: column;
        }

        /* Top Header Placeholder */
        .header-placeholder {
            height: 100px;
            width: 100%;
            margin-bottom: 5px;
            /* border-bottom: 2px solid var(--primary-green); */
        }

        .title-block {
            text-align: center;
            font-weight: bold;
            font-size: 13pt;
            text-transform: uppercase;
            margin: 10px 0;
            line-height: 1.1;
        }

        .address {
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 15px;
        }

        /* Grid for Page 1 / Page 9 sections */
        .content-grid {
            display: grid;
            grid-template-columns: 70px 1fr;
            gap: 5px;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .label-bold { font-weight: bold; }
        .red-num { color: var(--accent-red); }
        .red-bold { color: var(--accent-red); font-weight: bold; }
        .caps-bold { font-weight: bold; text-transform: uppercase;color: var(--accent-red); }

        .section-center {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            margin: 15px 0 8px 0;
            font-size: 11pt;
        }

        .highlight-box {
            color: var(--accent-red);
            font-weight: bold;
            text-align: justify;
            margin-bottom: 10px;
        }

        /* Terms List */
        .terms {
            list-style: none;
            padding-left: 15px;
            margin: 10px 0;
        }
        .terms li { margin-bottom: 3px; }

        /* Signature Blocks */
        .sig-row {
            display: flex;
            justify-content: space-between;
            margin-top: 35px;
            text-align: center;
        }
        .sig-col { width: 40%; }
        .line { border-top: 1px solid #000; padding-top: 3px; font-weight: bold; font-size: 9.5pt; }

        /* Bottom Section */
        .approval-footer {
            margin-top: auto; /* Pushes footer to bottom */
            border-top: 2px solid var(--primary-green);
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .status-stamp {
            text-align: center;
            color: var(--accent-red);
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12pt;
            margin: 25px 0;
        }

        /* Header Logos */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100px;
            width: 100%;
            margin-bottom: 5px;
          
            padding-bottom: 6px;
        }
        .doc-header img {
            height: 85px;
            width: auto;
            object-fit: contain;
        }
        .doc-header .header-center {
            text-align: center;
            flex: 1;
            padding: 0 8px;
            font-size: 9.5pt;
            font-weight: bold;
            
            line-height: 1.3;
        }

        /* Footer Logos */
        .doc-footer-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            margin-top: 8px;
        }
        .doc-footer-logos img {
            height: 35px;
            width: auto;
            object-fit: contain;
        }

        /* Printing logic to force one page */
        @media print {
            body { background: none; }
            .a4-page { 
                margin: 0; 
                box-shadow: none; 
                width: 100%;
                height: 100%;
            }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<div class="a4-page">
    <div class="side-placeholder"></div>
    
    <div class="main-container">
        <div class="doc-header hidden">
       
           
                
        </div>

        <div class="title-block">
            Recommendation for the Conversion of<br>
            Customary Title to SLTR Statutory  Right of Occupancy
        </div>

        <div class="address">
            Honourable Commissioner<br>
            Ministry of Land and Physical Planning<br>
            Kano State.
        </div>

        <div class="content-grid">
            <div class="label-bold">Page <span class="red-num">1</span></div>
            <div>
                Application by: <span class="caps-bold">{{ strtoupper($recommendation->applicant_name ?? 'N/A') }}</span><br>
                for the right of Occupancy <span class="label-bold">over a piece of Land</span> at <span class="red-bold">{{ $recommendation->location ?? 'N/A' }}</span> in <span class="red-bold">{{ $recommendation->lga ?? 'N/A' }}</span> Local Government Area.<br>
                for the purpose of <span class="red-bold">{{ $recommendation->purpose_of_clause ?? 'N/A' }}{{ $recommendation->sltr_number ? ' ('.$recommendation->sltr_number.')' : '' }}.</span>
            </div>
        </div>

        <div class="content-grid">
            <div class="label-bold">Page <span class="red-num">9</span></div>
            <div>
                Survey Report The <span class="label-bold">Correct description of the plot is a piece of Land at {{ $recommendation->location ?? 'N/A' }}</span>{{ $recommendation->plot_number ? ', Plot No. '.$recommendation->plot_number : '' }}, in <span class="label-bold">{{ $recommendation->lga ?? 'N/A' }} Local Government Area.</span>
            </div>
        </div>

        <div class="section-center">Planning Recommendation (If Any)</div>

        <div class="highlight-box">
            This is a case of SLTR process from customary title to statutory right of occupancy, As per physical planning recommendation at page 17 herein.
        </div>

        <p style="margin: 5px 0;">The grant of Right of occupancy is recommended on the terms set out as follows:</p>
        <ul class="terms">
            <li>a) Rent: <span class="red-bold">{{ $recommendation->ground_rent ? number_format($recommendation->ground_rent, 2).' per sq meter' : 'N/A' }}</span></li>
            <li>b) Term: <span class="red-bold">{{ $recommendation->term ? $recommendation->term.' Years' : 'N/A' }}</span></li>
            <li>c) Land Use: <span class="red-bold">{{ $recommendation->land_use ?? 'N/A' }}</span></li>
            @if($recommendation->processing_fee)
            <li>d) Processing Fee: <span class="red-bold">₦{{ number_format($recommendation->processing_fee, 2) }}</span></li>
            @endif
        </ul>

        <p style="text-align: justify; font-size: 12pt;">
            You may wish to approve this application on the terms set above and subject to Survey Report and recommendation of the Urban Board/Physical Planning Department at page <span class="red-bold">9 and 17 refer</span>
        </p>

        <div class="sig-row">
            <div class="sig-col"><div class="line">Rank</div></div>
            <div class="sig-col"><div class="line">Director SLTR</div></div>
        </div>

        <div class="approval-footer">
            <div class="section-center" style="margin-top: 0;">Approval for the Conversion of Customary<br>Title to SLTR Statutory Right of Occupancy</div>
            
            <p style="margin: 10px 0;">I recommend/do not recommend the application for a Grant over Plot No.: <span class="red-bold">{{ $recommendation->plot_number ?? '___________' }}</span> Plan No. Location <span class="red-bold">{{ $recommendation->location ?? 'N/A' }}</span></p>

            <div class="sig-row" style="margin-top: 25px;">
                <div class="sig-col"><div class="line">Permanent Secretary</div></div>
                <div class="sig-col"><div class="line">Date</div></div>
            </div>

            <div class="status-stamp">
                The Grant of Occupancy is hereby APPROVED/NOT APPROVED
            </div>

            <div class="sig-row" style="margin-top: 15px; margin-bottom: 10px;">
                <div class="sig-col"><div class="line">Honourable Commissioner</div></div>
                <div class="sig-col"><div class="line">Date</div></div>
            </div>
        </div>

        <div class="doc-footer-logos">
             <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" >
            <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="LAS Logo" style="height: 30px;">
           
        </div>
    </div>
</div>

<script>setTimeout(()=>window.print(),800);</script>
</body>
</html>