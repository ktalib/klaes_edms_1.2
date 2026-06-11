# PHS Token System Improvements - Requirements Document

## Overview
This document outlines the improvements needed for the PHS (Property History Search) token management system, including dynamic package configuration, payment validation, invoicing, and document upload capabilities.

---

## Implementation Status

| # | Feature | Status |
|---|---------|--------|
| 1 | Dynamic Token Package Management | ✅ Done |
| 2 | Payment Validation for Bank Transfers | ✅ Done |
| 3 | E-Invoice Generation | ✅ Done |
| 4 | CAC Document Upload | ✅ Done |

### Implementation notes / deviations from this spec

- **Single admin controller.** Package CRUD and invoice verification were added to the existing `App\Http\Controllers\Phs\PhsAdminController` (not a new `PhsPackageAdminController`), to match how all other PHS staff admin is organized. Routes live in `routes/app3.php` under the `system-admin/phs` group, names `system-admin.phs.packages.*`.
- **Seed values kept current.** `phs_token_packages` was seeded with the live prices (Starter 2,000 / Professional 5,000 / Enterprise 10,000) rather than the spec's 1,000 Starter, so existing pricing did not silently change.
- **`packages()` contract preserved.** `PhsTokenController::packages()` still returns `slug => ['name','tokens','price', ...]` so every existing caller (onboarding form, registration credit, dashboard purchase modal, org console) keeps working. It now reads the DB with a hardcoded fallback if the table is missing.
- **Payment validation lives on `phs_token_transactions`** (the invoice/top-up flow), which is where the pending→approve lifecycle already existed. The spec's mention of putting payment status on `phs_onboarding_requests` was not used; the initial onboarding payment remains on the request, while invoice top-ups carry the new validation fields.
- **Verification UI:** new page `system-admin/phs/pending-invoices` lists `pending` invoice transactions with a "Verify & Approve" modal (amount paid, bank reference, payment date, proof upload, notes). `approveInvoice` computes the variance, sets `validation_status` (verified/incomplete/overpaid), records the validator, and credits tokens. The Subscriptions page shows the resulting payment badge.
- **Two-database note:** all PHS migrations target the `sqlsrv` connection and are guarded with `hasTable`/`hasColumn` so they are safe to re-run.
- **Onboarding payment verification (added on request):** `phs_onboarding_requests` now carries `payment_status` (not_paid / incomplete / completed / overpaid), `expected_amount`, `verified_amount`, `outstanding_amount`, and `payment_verified_at|by|notes`. Staff confirm the received amount from the request detail page (`requests.verify-payment`); the system derives the status and outstanding balance. Badges + outstanding balance show on the onboarding-requests list and detail page. This is separate from the workflow `status` and does not block approval.
- **Mail TLS (local dev):** Laravel 9's SMTP builder ignores stream/SSL config, so `config/mail.php` gained a `verify_peer` flag (`MAIL_VERIFY_PEER`, default **true**). When set to `false`, `AppServiceProvider` registers a custom `smtp` transport that disables TLS certificate verification (`verify_peer`/`verify_peer_name` off) — a local-only escape hatch for machines without a CA bundle. Production keeps verification on. The proper fix remains pointing `openssl.cafile`/`curl.cainfo` at a `cacert.pem` in php.ini.
- **CAC upload (Section 4):** `cac_registration_number`, `cac_document_path`, and `additional_documents` (JSON, cast to array) added to `phs_onboarding_requests`. Because the onboarding flow stores form data in the **session** between the form and payment steps (and files can't be serialized into the session), uploads are stored to disk in `confirmPayment()` and only their paths are kept in the session — then persisted on `submitRequest()`. CAC is **required** (PDF, ≤5MB); additional documents are optional (PDF/JPG/PNG, ≤5MB each). The onboarding form uses `enctype="multipart/form-data"`. Files live on the `public` disk under `phs/cac-documents` and `phs/additional-documents`; the admin review page (`onboarding-request-show`) links to them via `asset('storage/...')`.
- **Invoice generation (Section 3):** invoice fields added to `phs_onboarding_requests`; `generateInvoiceNumber()` produces `PHS-INV-YYYYMMDD-XXXX`. The PDF is built with DomPDF from `resources/views/phs/invoice-template.blade.php` and stored on the `public` disk under `phs/invoices/`. It is generated on `submitRequest`, attached to the admin notification email (`PhsOnboardingRequestSubmitted::attachments()`), downloadable from the request-pending page (`phs.request.invoice`) and the org console Subscription tab (`phs.org.invoice.download`). `invoice_number` is a plain nullable column (uniqueness enforced in code) to avoid SQL Server's multiple-NULL unique-index limitation. The template uses `NGN` rather than the ₦ glyph, which DejaVu/DomPDF cannot render. Bank details read from optional `config('phs.bank_name'|'bank_account')`.

---

## 1. Dynamic Token Package Management

### Current State
Token packages are hardcoded in `PhsTokenController::packages()`:
- Starter: 2,000 tokens @ ₦50,000
- Professional: 5,000 tokens @ ₦100,000  
- Enterprise: 10,000 tokens @ ₦180,000

### Required Changes

#### 1.1 Database Structure
Create new table: `phs_token_packages`

```sql
CREATE TABLE phs_token_packages (
    id BIGINT PRIMARY KEY IDENTITY(1,1),
    name NVARCHAR(100) NOT NULL,
    slug NVARCHAR(50) NOT NULL UNIQUE,
    tokens INT NOT NULL,
    price DECIMAL(18,2) NOT NULL,
    team_members INT NOT NULL DEFAULT 2,
    description NVARCHAR(500) NULL,
    is_active BIT NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE()
);

-- Insert default packages
INSERT INTO phs_token_packages (name, slug, tokens, price, team_members, description, display_order) VALUES
('Starter', 'starter', 1000, 50000, 2, '1 bundle (1K tokens) for small teams', 1),
('Professional', 'professional', 5000, 100000, 5, 'Multi-bundle package for growing teams', 2),
('Enterprise', 'enterprise', 10000, 180000, 10, 'Large-scale package for enterprise teams', 3);
```

#### 1.2 Model Creation
Create `app/Models/Phs/PhsTokenPackage.php`

```php
<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsTokenPackage extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_token_packages';

    protected $fillable = [
        'name',
        'slug',
        'tokens',
        'price',
        'team_members',
        'description',
        'is_active',
        'display_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('display_order');
    }
}
```

#### 1.3 Admin Management Interface
Create admin routes and controller for package management:

**Routes:** `routes/web.php` or `routes/phs.php`
```php
Route::middleware(['auth', 'role:super-admin'])->prefix('system-admin/phs')->name('system-admin.phs.')->group(function () {
    Route::get('/packages', [PhsPackageAdminController::class, 'index'])->name('packages.index');
    Route::post('/packages', [PhsPackageAdminController::class, 'store'])->name('packages.store');
    Route::put('/packages/{package}', [PhsPackageAdminController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}', [PhsPackageAdminController::class, 'destroy'])->name('packages.destroy');
});
```

**View:** Create `resources/views/system-admin/phs/packages.blade.php` with:
- List all packages with edit/delete actions
- Add new package form
- Fields: name, slug, tokens, price, team_members, description, is_active, display_order

#### 1.4 Update PhsTokenController
Replace hardcoded packages with database query:

```php
public static function packages(): array
{
    return PhsTokenPackage::active()
        ->get()
        ->keyBy('slug')
        ->map(fn($p) => [
            'name' => $p->name,
            'tokens' => $p->tokens,
            'price' => $p->price,
            'team_members' => $p->team_members,
            'description' => $p->description
        ])
        ->toArray();
}
```

---

## 2. Payment Validation for Bank Transfers

### Current State
The `phs_token_transactions` table has basic payment fields but lacks comprehensive validation fields.

### Required Changes

#### 2.1 Database Migration
Add payment validation fields to `phs_token_transactions`:

```sql
ALTER TABLE phs_token_transactions ADD
    expected_amount DECIMAL(18,2) NULL,
    paid_amount DECIMAL(18,2) NULL,
    payment_variance DECIMAL(18,2) NULL,
    payment_proof_path NVARCHAR(500) NULL,
    bank_reference NVARCHAR(255) NULL,
    payment_date DATE NULL,
    validation_status NVARCHAR(20) NULL DEFAULT 'pending', -- pending | verified | incomplete | overpaid
    validation_notes NVARCHAR(1000) NULL,
    validated_by BIGINT NULL,
    validated_at DATETIME2 NULL;
```

#### 2.2 Update PhsTokenTransaction Model
Add new fillable fields and relationships:

```php
protected $fillable = [
    // ... existing fields ...
    'expected_amount',
    'paid_amount',
    'payment_variance',
    'payment_proof_path',
    'bank_reference',
    'payment_date',
    'validation_status',
    'validation_notes',
    'validated_by',
    'validated_at',
];

protected $casts = [
    'expected_amount' => 'decimal:2',
    'paid_amount' => 'decimal:2',
    'payment_variance' => 'decimal:2',
    'payment_date' => 'date',
    'validated_at' => 'datetime',
];

public function validator()
{
    return $this->belongsTo(User::class, 'validated_by');
}

public function isPaymentComplete(): bool
{
    return $this->validation_status === 'verified' 
        && $this->paid_amount >= $this->expected_amount;
}

public function hasPaymentShortfall(): bool
{
    return $this->paid_amount < $this->expected_amount;
}
```

#### 2.3 Pending Requests Page Enhancement
Update `resources/views/system-admin/phs/onboarding-requests.blade.php` to show:
- Payment status badge (Complete, Incomplete, Overpaid, Pending Verification)
- Expected vs Paid amount comparison
- Payment variance indicator
- Filter options: All | Complete | Incomplete | Pending

Add status indicator:
```blade
@if($request->validation_status === 'verified')
    <span class="badge badge-success">Payment Complete</span>
@elseif($request->validation_status === 'incomplete')
    <span class="badge badge-warning">Incomplete Payment (₦{{ number_format($request->payment_variance) }} short)</span>
@elseif($request->validation_status === 'overpaid')
    <span class="badge badge-info">Overpaid (₦{{ number_format(abs($request->payment_variance)) }} excess)</span>
@else
    <span class="badge badge-secondary">Pending Verification</span>
@endif
```

---

## 3. E-Invoice Generation

### Required Features

#### 3.1 Database Structure
Add invoice fields to `phs_onboarding_requests`:

```sql
ALTER TABLE phs_onboarding_requests ADD
    invoice_number NVARCHAR(100) NULL UNIQUE,
    invoice_generated_at DATETIME2 NULL,
    invoice_sent_at DATETIME2 NULL,
    invoice_pdf_path NVARCHAR(500) NULL;
```

#### 3.2 Invoice Number Generation
Format: `PHS-INV-YYYYMMDD-XXXX`

```php
public function generateInvoiceNumber(): string
{
    $date = now()->format('Ymd');
    $lastInvoice = PhsOnboardingRequest::whereNotNull('invoice_number')
        ->whereDate('created_at', today())
        ->orderBy('id', 'desc')
        ->first();
    
    $sequence = $lastInvoice ? (int)substr($lastInvoice->invoice_number, -4) + 1 : 1;
    return 'PHS-INV-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
```

#### 3.3 Invoice Template
Create `resources/views/phs/invoice-template.blade.php`:

**Invoice should include:**
- KLAES Ministry header & logo
- Invoice number and date
- Organization details
- Contact person details
- Itemized breakdown:
  - Package name
  - Token quantity
  - Team member count
  - Unit price
  - Total amount
- Payment instructions (bank details)
- Payment reference format
- Terms and conditions
- Footer with ministry contact info

#### 3.4 PDF Generation
Use DomPDF (already available in the project):

```php
use Barryvdh\DomPDF\Facade\Pdf;

public function generateInvoicePdf(PhsOnboardingRequest $request)
{
    $package = PhsTokenPackage::where('name', $request->initial_token_package)->first();
    
    $data = [
        'request' => $request,
        'package' => $package,
        'invoice_number' => $request->invoice_number ?: $request->generateInvoiceNumber(),
        'invoice_date' => now(),
    ];
    
    $pdf = Pdf::loadView('phs.invoice-template', $data);
    $filename = 'invoice-' . $data['invoice_number'] . '.pdf';
    $path = 'invoices/phs/' . $filename;
    
    Storage::disk('public')->put($path, $pdf->output());
    
    $request->update([
        'invoice_number' => $data['invoice_number'],
        'invoice_pdf_path' => $path,
        'invoice_generated_at' => now(),
    ]);
    
    return $pdf;
}
```

#### 3.5 Email Integration
Update `PhsOnboardingRequestSubmitted` mail class to attach invoice:

```php
public function attachments(): array
{
    if ($this->request->invoice_pdf_path) {
        return [
            Attachment::fromStorageDisk('public', $this->request->invoice_pdf_path)
                ->as('Invoice-' . $this->request->invoice_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
    return [];
}
```

#### 3.6 Organization Dashboard Access
Add invoice download link in org dashboard:
- Route: `GET /phs/organization/invoice/download`
- Show invoice number, date, amount, status
- Printable view option
- Re-download capability

---

## 4. CAC Document Upload

### Current State
The onboarding request form collects organization information but doesn't allow document uploads.

### Required Changes

#### 4.1 Database Migration
Add document fields to `phs_onboarding_requests`:

```sql
ALTER TABLE phs_onboarding_requests ADD
    cac_document_path NVARCHAR(500) NULL,
    cac_registration_number NVARCHAR(100) NULL,
    additional_documents TEXT NULL; -- JSON array of additional document paths
```

#### 4.2 Update Onboarding Form
Modify `resources/views/phs/onboarding-request-form.blade.php`:

Add new section after "Organization Information":

```html
<div class="section-divider"></div>

<!-- CAC Documentation -->
<div class="section-title">
    <span class="icon">
        <svg viewBox="0 0 24 24">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
        </svg>
    </span>
    CAC Documentation
</div>

<div class="field-row">
    <div class="field">
        <label class="field-label">CAC Registration Number <span class="req">*</span></label>
        <input type="text" name="cac_registration_number" 
               value="{{ old('cac_registration_number') }}" 
               required 
               placeholder="e.g. RC123456" 
               class="field-input">
    </div>
    <div class="field">
        <label class="field-label">CAC Certificate (PDF only) <span class="req">*</span></label>
        <input type="file" 
               name="cac_document" 
               accept=".pdf" 
               required 
               class="field-input">
        <small class="field-hint">Maximum file size: 5MB</small>
    </div>
</div>

<div class="field">
    <label class="field-label">Additional Documents (Optional)</label>
    <input type="file" 
           name="additional_documents[]" 
           accept=".pdf,.jpg,.jpeg,.png" 
           multiple 
           class="field-input">
    <small class="field-hint">Supporting documents (ID, authorization letter, etc.) - Max 5MB each</small>
</div>
```

#### 4.3 Controller Validation & Storage
Update `PhsOnboardingController`:

```php
public function confirm(Request $request)
{
    $validated = $request->validate([
        // ... existing validations ...
        'cac_registration_number' => 'required|string|max:100',
        'cac_document' => 'required|file|mimes:pdf|max:5120', // 5MB
        'additional_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
    ]);
    
    // Store CAC document
    if ($request->hasFile('cac_document')) {
        $cacPath = $request->file('cac_document')->store('phs/cac-documents', 'public');
        $validated['cac_document_path'] = $cacPath;
    }
    
    // Store additional documents
    $additionalDocs = [];
    if ($request->hasFile('additional_documents')) {
        foreach ($request->file('additional_documents') as $file) {
            $additionalDocs[] = $file->store('phs/additional-documents', 'public');
        }
    }
    $validated['additional_documents'] = json_encode($additionalDocs);
    
    PhsOnboardingRequest::create($validated);
    
    // ... rest of logic ...
}
```

#### 4.4 Admin Review Interface
Update `resources/views/system-admin/phs/onboarding-request-show.blade.php`:

Add document viewing section:

```blade
<div class="document-section">
    <h3>CAC Documentation</h3>
    
    <div class="field-display">
        <label>CAC Registration Number:</label>
        <span>{{ $request->cac_registration_number }}</span>
    </div>
    
    <div class="field-display">
        <label>CAC Certificate:</label>
        @if($request->cac_document_path)
            <a href="{{ Storage::url($request->cac_document_path) }}" 
               target="_blank" 
               class="btn-view-document">
                <i data-lucide="file-text"></i> View CAC Certificate
            </a>
        @else
            <span class="text-muted">Not uploaded</span>
        @endif
    </div>
    
    @if($request->additional_documents)
        <div class="field-display">
            <label>Additional Documents:</label>
            <ul class="document-list">
                @foreach(json_decode($request->additional_documents) as $index => $doc)
                    <li>
                        <a href="{{ Storage::url($doc) }}" target="_blank">
                            <i data-lucide="paperclip"></i> Document {{ $index + 1 }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
```

---

## 5. Implementation Summary

### Database Migrations Required
1. Create `phs_token_packages` table
2. Alter `phs_token_transactions` - add payment validation fields
3. Alter `phs_onboarding_requests` - add invoice & CAC fields

### New Models
1. `PhsTokenPackage`

### Controllers to Create/Update
1. `PhsPackageAdminController` (new)
2. `PhsTokenController` (update)
3. `PhsOnboardingController` (update)
4. Invoice generation logic in admin controller

### Views to Create/Update
1. `resources/views/system-admin/phs/packages.blade.php` (new)
2. `resources/views/phs/invoice-template.blade.php` (new)
3. `resources/views/phs/onboarding-request-form.blade.php` (update)
4. `resources/views/system-admin/phs/onboarding-request-show.blade.php` (update)
5. `resources/views/system-admin/phs/onboarding-requests.blade.php` (update)
6. Organization dashboard invoice section (new)

### Routes to Add
1. Package management routes (CRUD)
2. Invoice generation & download routes
3. Payment validation routes

---

## 6. Testing Checklist

- [ ] Create/edit/delete token packages from admin panel
- [ ] Verify packages display correctly on onboarding form
- [ ] Test bank transfer payment with various amounts (complete, incomplete, overpaid)
- [ ] Verify payment status indicators on pending requests page
- [ ] Generate invoice PDF with correct calculations
- [ ] Send invoice via email with attachment
- [ ] Download invoice from organization dashboard
- [ ] Upload CAC document (PDF validation, size limit)
- [ ] Upload additional documents (multiple files)
- [ ] View uploaded documents in admin review page
- [ ] Test complete onboarding flow with all new features

---

## 7. Security Considerations

1. **File Upload Security**
   - Validate file MIME types
   

2. **Payment Validation**
   - Require admin approval for payment verification
   - Log all payment status changes
   - Prevent token crediting until payment verified

 