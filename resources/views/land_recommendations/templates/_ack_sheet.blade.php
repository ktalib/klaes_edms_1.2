{{-- Acknowledgement sheet — rendered as the second page of the Land Recommendation print.
     All selectors are scoped under .ack-page so they do not collide with print_layout's styles. --}}
<style>
  .ack-page {
    page-break-before: always;
    position: relative;
    min-height: 25.5cm;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
  }

  .ack-page .header {
    position: relative;
    text-align: center;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    margin-bottom: 16px;
  }

  .ack-page .header-logo {
    position: absolute;
    top: 0;
    right: 0;
    width: 70px;
    height: 70px;
    object-fit: contain;
  }

  .ack-page .header-logo-left {
    position: absolute;
    top: 0;
    left: 0;
    width: 70px;
    height: 70px;
    object-fit: contain;
  }

  .ack-page .header-title {
    font-size: 17px;
    font-weight: bold;
    text-decoration: underline;
    text-transform: uppercase;
    margin-bottom: 6px;
  }

  .ack-page .header-subtitle {
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .ack-page .content-body p {
    font-size: 14px;
    line-height: 1.35;
    margin-bottom: 10px;
    text-align: justify;
  }

  .ack-page .content-body a {
    color: #0066cc;
    text-decoration: underline;
  }

  .ack-page .contact-section {
    margin-top: 14px;
    margin-bottom: 16px;
    font-size: 14px;
    line-height: 1.3;
  }

  .ack-page .contact-section h3 {
    font-size: 15px;
    font-weight: bold;
    margin-bottom: 8px;
  }

  .ack-page .contact-address {
    margin-bottom: 4px;
  }

  .ack-page .acknowledgment-title {
    font-size: 15px;
    font-weight: 500;
    margin-top: 16px;
    margin-bottom: 20px;
  }

  .ack-page .form-group {
    margin-bottom: 34px;
    display: flex;
    align-items: flex-end;
    font-size: 14.5px;
  }

  .ack-page .form-label {
    font-weight: 500;
    white-space: nowrap;
    margin-right: 2px;
  }

  .ack-page .dotted-line {
    flex-grow: 1;
    border-bottom: 1px dotted #000;
    margin-left: 2px;
    height: 1.2em;
  }

  .ack-page .dotted-line.short {
    max-width: 320px;
  }

  .ack-page .dotted-line.medium {
    max-width: 420px;
  }

  .ack-page .multiline-spacer {
    margin-top: 26px;
  }

  .ack-page .form-row {
    display: flex;
    gap: 40px;
    margin-bottom: 34px;
  }

  .ack-page .form-row .form-group {
    flex: 1;
    margin-bottom: 0;
  }

  .ack-page .signature-block {
    margin-top: 40px;
  }

  .ack-page .signature-block-title {
    font-weight: bold;
    font-size: 14.5px;
    margin-bottom: 28px;
    padding-bottom: 6px;
    border-bottom: 1px solid #000;
  }

  .ack-page .footer {
    position: absolute;
    bottom: 0;
    right: 0;
  }

  .ack-page .footer img {
    height: 40px;
    object-fit: contain;
  }
</style>

<div class="ack-page">

  <!-- Top Header -->
  <header class="header">
    <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Kano State Ministry Logo" class="header-logo-left">
    <img src="http://app.klaes.ng/assets/logo/ministry2.png" alt="Kano State Ministry Logo" class="header-logo">
    <h1 class="header-title">Right of Occupancy</h1>
    <h2 class="header-subtitle">Collection of Letter of Grant</h2>
  </header>

  <!-- Paragraphs -->
  <section class="content-body">
    <p>
      Please note that you may be invited later for an Interview via Phone, SMS, WhatsApp or Email to provide additional information and documentation where necessary. You can always check the status of your application via our website [<a href="https://land.gov.ng" target="_blank">https://land.gov.ng</a>] or Contact the Ministry of Land Customer Service Desk via Phone: &nbsp;&nbsp;&nbsp;&nbsp;, SMS, WhatsApp, or you can visit the Land Customer Service Desk.
    </p>

    <p>
      You can track the progress of your application using the QR code on this page
    </p>

    <p>
      Please keep the original acknowledgement letter in a safe place for future reference. It is one of the requirements for the collection of your new digital Certificate of Occupancy.
    </p>
  </section>

  <!-- Contact Details -->
  <section class="contact-section">
    <h3>Contact Information:</h3>
    <div class="contact-address">
      Land Customer Service<br>
      2 Dr. Bala Muhammad Way,<br>
      Nassarawa G.R.A., Kano, Nigeria<br>
      Tel: +234 (0)900 000 0000 | Email: support@kangis.gov.ng<br>
      Website: <a href="https://Land.gov.ng" target="_blank">https://Land.gov.ng</a>
    </div>
  </section>

  <!-- Acknowledgment Title -->
  <div class="acknowledgment-title">
    Original copy of acknowledgement letter was collected by me
  </div>

  <!-- Fillable Form Fields -->
  <section class="form-container">

    <div class="form-group">
      <span class="form-label">Name</span>
      <div class="dotted-line short"></div>
    </div>

    <div class="form-group">
      <span class="form-label">Address:</span>
      <div class="dotted-line short"></div>
    </div>

    <!-- Second Line for Address -->
    <div class="form-group multiline-spacer">
      <div class="dotted-line medium"></div>
    </div>

    <div class="form-group">
      <span class="form-label">Phone No:</span>
      <div class="dotted-line medium"></div>
    </div>

    <!-- Recipient Signature -->
    <div class="form-row signature-block">
      <div class="form-group">
        <span class="form-label">Signature</span>
        <div class="dotted-line short"></div>
      </div>
      <div class="form-group">
        <span class="form-label">Date</span>
        <div class="dotted-line short"></div>
      </div>
    </div>

    <!-- Witness Signature -->
    <div class="signature-block">
      <div class="signature-block-title">Witness</div>

      <div class="form-group">
        <span class="form-label">Name</span>
        <div class="dotted-line short"></div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <span class="form-label">Signature</span>
          <div class="dotted-line short"></div>
        </div>
        <div class="form-group">
          <span class="form-label">Date</span>
          <div class="dotted-line short"></div>
        </div>
      </div>
    </div>

  </section>

  <!-- Footer -->
  <footer class="footer">
    <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="KLAES Logo">
  </footer>

</div>
