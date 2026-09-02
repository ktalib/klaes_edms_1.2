# Online Legal Search — ID Name Verification

## What this feature does, and what it does not

Before an applicant may pay for a public Online Legal Search, they supply their
identification details and images of a government-issued ID. The server reads the
document with OCR and compares the name printed on it against the name they typed.
Only a `verified` result opens the Paystack checkout.

**This verifies one thing: that the entered name matches text found on the uploaded
document.** It is deliberately described everywhere — in code, in the UI, and in this
document — as *ID name verification*, never as identity verification. It is **not**
evidence that:

- the document is genuine;
- the document has not been altered;
- the person uploading it is the person it depicts;
- the ID number is valid with the issuing authority.

Anyone extending this feature must preserve that distinction. A `verified` status is
a name match and nothing more.

---

## Server installation (required)

The OCR engine is **not** bundled with the application. The PHP package shells out to
a locally installed `tesseract` binary; without it every verification returns the
"unreadable document" outcome and logs a `Tesseract binary not found` error — the
applicant simply cannot get past the form.

**KLAES production runs on Windows Server.** The Windows instructions below are the
ones that apply to this deployment.

### 1. PHP package (both environments)

```bash
composer require thiagoalessio/tesseract_ocr
```

If you deploy by uploading code rather than running composer on the server, `vendor/`
must be uploaded too.

### 2. Tesseract on Windows Server (production and WAMP development)

Install the UB-Mannheim build — the standard Windows distribution:

<https://github.com/UB-Mannheim/tesseract/wiki>

During installation, keep the **English** language data selected. The default
install path is:

```
C:\Program Files\Tesseract-OCR\tesseract.exe
```

Then point the application at it. **Setting `TESSERACT_PATH` explicitly is the
recommended route on Windows** — see the PATH warning below:

```env
TESSERACT_PATH='C:\Program Files\Tesseract-OCR\tesseract.exe'
```

> **Use SINGLE quotes.** Laravel's dotenv parser treats a backslash inside *double*
> quotes as an escape sequence, so `TESSERACT_PATH="C:\Program Files\..."` fails with
> *"Failed to parse dotenv file. Encountered an unexpected escape sequence"* — and that
> takes the **whole application** down, not just OCR. Single quotes are literal.
> `TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"` (forward slashes) also
> works if you prefer double quotes.

`.env` is gitignored, so this line is **not** carried over by a code upload. It must be
added on the server itself, and `php artisan config:clear` run afterwards if the config
is cached.

#### Two Windows-specific traps

1. **PATH belongs to the account, not the machine.** Adding the Tesseract folder to the
   system PATH does not help until the **IIS or Apache service is restarted** — a
   long-running service keeps the environment it started with. Worse, `tesseract` will
   work perfectly from your own Administrator console while the web-server service
   account still cannot see it, so a manual check proves nothing. Setting
   `TESSERACT_PATH` avoids the whole problem.
2. **Permissions.** The account the web server runs as (the IIS AppPool identity, or the
   Apache service user) needs **Read & Execute** on `tesseract.exe` and **Read** on the
   `tessdata` folder beside it, plus **Modify** on `storage\app\private` where ID
   images are written.

### 3. Verify — with the doctor command, not by eye

```bash
php artisan ols:id-verification-doctor
```

Run it **on the server**, and ideally as the account the web server runs as. It checks
the package, the binary path, whether the binary actually executes, whether the private
disk is writable, and which thresholds are in force — and names the fix for whatever
fails. To confirm a real document end to end:

```bash
php artisan ols:id-verification-doctor --image=C:\path\to\a\sample-id.jpg
```

That prints what OCR read from the file, which is the fastest way to tell an engine
problem from a poor-quality photograph.

### If this is ever deployed to Linux

Not the current target, recorded only so the option is documented:

```bash
sudo apt update && sudo apt install -y tesseract-ocr tesseract-ocr-eng
tesseract --version
```

> Nothing in the codebase installs system packages. Every command in this section is
> for an operator to run deliberately.

## Configuration

All tuning lives in `config/id_verification.php`; nothing is hard-coded in the
controller or the service.

| Key | Env var | Default | Purpose |
|---|---|---|---|
| `thresholds.verified` | `ID_VERIFICATION_VERIFIED_THRESHOLD` | `80` | Score at or above which the applicant may pay |
| `thresholds.review` | `ID_VERIFICATION_REVIEW_THRESHOLD` | `60` | Score at or above which a human should look |
| `min_matching_parts` | `ID_VERIFICATION_MIN_PARTS` | `2` | Name components that must match before `verified` is possible |
| `max_edit_distance` | `ID_VERIFICATION_MAX_EDIT_DISTANCE` | `1` | Per-component tolerance for OCR noise |
| `store_raw_text` | `ID_VERIFICATION_STORE_RAW_TEXT` | `false` | Keep the OCR transcript for auditing |
| `retention_days` | `ID_VERIFICATION_RETENTION_DAYS` | `null` | Intended document retention window |
| `uploads.max_kilobytes` | `ID_VERIFICATION_MAX_KB` | `5120` | Per-image size cap |
| `ocr.binary` | `TESSERACT_PATH` | `null` (use `PATH`) | Absolute path to the binary |
| `preprocess.enabled` | `ID_VERIFICATION_PREPROCESS` | `true` | Orientation/resize/grayscale/contrast before OCR |

### Score bands

| Score | Status | Effect |
|---|---|---|
| 80–100 | `verified` | Payment may proceed |
| 60–79 | `review` | Stays on the form; no payment transaction |
| below 60 | `failed` | Stays on the form; no payment transaction |

A result also needs at least `min_matching_parts` matching components to be
`verified`. One matching name is never enough on its own — "IBRAHIM" alone matches a
large share of the register.

---

## Matching rules

Implemented in `app/Services/IdNameVerificationService.php`, which takes two strings
and returns a verdict. It knows nothing about OCR engines, HTTP or storage, so it is
unit-testable without Tesseract installed.

Normalization:

- upper-cased, so capitalization alone never fails a match;
- punctuation and separators become spaces, so `O'BRIEN`, `MARY-JANE` and
  `SURNAME:` all split into plain components;
- accents folded (`ADÉLE` = `ADELE`), via `intl` where available and an explicit
  Unicode-derived map otherwise — `iconv //TRANSLIT` is deliberately not used, as its
  output is platform-dependent and renders `é` as `'e` on some stacks, which would
  split one name into two bogus components;
- repeated whitespace collapsed;
- single-character components dropped as initials;
- duplicates counted once.

Comparison is order-independent: every entered component is looked for anywhere among
the document's components, so `Iorkua Kator Daniel` matches `DANIEL IORKUA KATOR` at
100%.

Common OCR glyph confusions (`0`/`O`, `1`/`I`, `5`/`S`, `8`/`B`, `2`/`Z`, `6`/`G`) are
folded for comparison only. A bounded Levenshtein pass (distance 1, names of 5+
characters) covers the rest.

### Observed on a real Nigerian NIN slip

A production digital NIN slip OCRs to roughly:

```
FEDERAL REPUBLIC OF NIGERIA / DIGITAL NIN SLIP / SURAMENGR / TORKUA /
KATOR, DANIEL / 30 NOV 1996 M / National Identification Number (NIN) / 2902 435 5525
```

Two things to notice. The engine misread `IORKUA` as `TORKUA` — a capital I read as a
T, which the glyph-confusion map does *not* cover; the bounded Levenshtein pass
(distance 1 on names of 5+ characters) is what rescues it. And the surrounding card
text (`FEDERAL`, `REPUBLIC`, `NIN`, the number itself) is harmless, because only the
components the applicant *typed* have to be found — extra words on the document are
ignored. An applicant entering "Iorkua Daniel" against that slip scores 100 and
verifies.

**Substrings are not matches.** `DAN` does not match `DANIEL`: a shortened name is a
different name.

---

## Workflow

1. Applicant enters email and confirms it (existing payment card).
2. Applicant fills the Identify your Customer (IYC) card and uploads **one** photo of their
   ID - the side carrying the name. A back image was required at first for two-sided
   cards, but the name is printed on the front of every accepted document, so the second
   upload cost an extra step and a second OCR pass while adding nothing to the
   comparison. `id_back_path` is kept on the table (nullable) for rows captured before
   the change, and the reviewer route still serves them.
3. The check runs **automatically** — there is no "verify" button. The browser posts to
   `POST /online-legal-search/verification` once every required field is filled and an
   image is attached, and again whenever one of those answers changes. Three guards stop
   that being wasteful, since every run uploads an image and costs an OCR pass: a 900ms
   debounce, a signature of the submitted answers so nothing unchanged is re-sent, and an
   in-flight flag so a slow check is never overlapped. The route is rate limited to
   12/minute regardless.
4. `StoreIdVerificationRequest` validates everything server-side.
5. The image is stored on the private `ols_private` disk under a generated filename.
6. A **temporary** preprocessed copy is OCR'd; the temp file is deleted in a `finally`
   block, including when OCR throws. The stored original is never modified.
7. Name comparison runs; the row is written with the server-computed status and score.
8. Only `verified` unlocks the checkout.
9. `verifyPayment` re-checks the session-bound verified row before recording any
   payment, so a `review` or `failed` result cannot produce a payment transaction.

### What the applicant sees when something is wrong

| Situation | Message | Stored status |
|---|---|---|
| Name matches | "Your identification name has been verified successfully." | `verified` |
| Name partly matches | "We could not confidently verify the full name..." | `review` |
| Name does not match | "The full name entered does not match..." | `failed` |
| OCR ran, found no text | "We could not read the uploaded identification..." | `failed` |
| **OCR engine unavailable** | "Identification checks are temporarily unavailable... this is a problem on our side, not with your document." | `pending` |

The last row matters: a missing or unrunnable `tesseract` binary is **not** a bad
photograph, and telling the applicant it is would send them re-photographing a
perfectly good ID indefinitely. That case is also stored as `pending`, not `failed` —
no comparison ever ran, so recording a name mismatch against the applicant would be
untrue. When applicants report this message, run
`php artisan ols:id-verification-doctor` on the server; it is always an installation or
permissions problem, never the document.

The status and score are **always** computed server-side. No value from the browser is
read at any point in this flow.

---

## Security and privacy

- ID images live on the `ols_private` disk (`storage/app/private`), outside the
  `public` disk and therefore unreachable through the `/storage` symlink.
- The only read path is
  `GET /legal-search/online/admin/verifications/{id}/document/{side}`, behind the staff
  `auth` guard and the same Director / Deputy Director check
  (`LegalSearchApprovalService::isApprover()`) that guards the approval queue.
- Storage paths are never rendered into HTML nor handed to JavaScript. The model hides
  `id_front_path`, `id_back_path`, `id_ocr_text` and `session_token` from serialization.
- The verification is bound to the submitting browser session, and the token is never
  accepted from the request — one applicant cannot present another's verification.
- Raw OCR text is never logged and never returned to the browser. Exception detail goes
  to the log; the applicant sees one of four fixed messages.
- The submission route is rate limited (`throttle:6,1`): each call performs file writes
  and an OCR pass.
- CSRF applies as normal (the route is in the `web` group).

### Document retention — not yet automated

`config('id_verification.retention_days')` records the intended window. **Nothing
deletes documents automatically yet.** When a purge is added, it belongs in a scheduled
command that reads that value, selects `legal_search_online_verifications` rows whose
`created_at` is older than the window, deletes both files from the `ols_private` disk,
and nulls `id_front_path` / `id_back_path` while keeping the row for audit.

Superseded images *are* cleaned up today: re-verifying within a session deletes the
images the previous attempt stored.

---

## Database

Table `legal_search_online_verifications` (sqlsrv), created by
`2026_09_01_000000_create_legal_search_online_verifications_table.php`.

It is a separate table rather than columns on `legal_search_online_payments` because
verification happens **before** any payment exists — at write time there is no payment
row to attach to. `payment_id` and `request_id` are filled in once the payment clears,
and the requester's name and phone are fed from here into the approval request, so the
applicant is recorded once rather than twice.

Deployment follows the project's two-file convention:

```
database/sql/2026_09_01_create_legal_search_online_verifications.sql              -- run against SQL Server
database/sql/2026_09_01_create_legal_search_online_verifications_ledger.mysql.sql -- then against MySQL
```

`id_verification_status` is constrained in the database to
`pending | verified | review | failed`.

---

## Replacing the OCR provider

`App\Services\Ocr\OcrReader` is the only contract the workflow depends on. To swap
engines, implement it and add a branch to the binding in `AppServiceProvider::register`,
selected by `config('id_verification.ocr.driver')`. Nothing in the payment workflow
changes.

Implementations must throw `OcrException` on failure rather than returning an empty
string: the caller distinguishes "the document was unreadable" (the applicant can fix
it with a better photo) from "OCR itself broke" (they cannot).

---

## Tests

```bash
php artisan test --testsuite=Unit --filter=IdNameVerificationServiceTest
php artisan test --filter=IdVerificationTest
```

The unit tests exercise the real normalization and scoring logic and need no database
or OCR engine. The feature tests mock `OcrReader`, so the suite never depends on a local
Tesseract install; they skip themselves when the sqlsrv connection is unreachable.
