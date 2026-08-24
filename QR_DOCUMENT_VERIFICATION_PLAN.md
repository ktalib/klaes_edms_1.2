# KLAES QR Document Verification — Implementation Plan

**Status:** Phases 0–2 implemented (RofO live) · Phase 3 partial · Phase 4 partial
**Date:** 2026-08-23

> **Implementation note (2026-08-23).** Built and verified with
> `php artisan qr:doctor` plus end-to-end tests against live records.
>
> **Done:** local QR rendering across all 35 call sites; the three tables;
> `QrTokenService` / `QrPayloadReader` / `DocumentQrService`; the `DocumentType`
> enum; `qr:doctor`; the verification endpoint and console; the Q1 decode guard
> on `/filetracker/get-file-info`; **RofO printing the signed token**, with the
> print audit hooked into `logPrint()` and `batchPrintLog()`; re-issuance;
> throttling on the verify endpoint.
>
> **Five things the build changed about the design:**
>
> 1. **35 `api.qrserver.com` call sites, not 6.**
> 2. **The token nonce had to become deterministic.** Approach A requires every
>    reprint to carry the *same* QR string, and the row stores only a hash — a
>    random nonce meant no reprint matched its stored hash. Now derived by HMAC
>    from the key and plaintext (§5).
> 3. **Four tracking-ID grammars in production, not two** — including bare
>    sequential integers on 348 of 375 RofO records (§7.1).
> 4. **The source uniqueness rule was wrong.** `UNIQUE (document_type, source_id)`
>    made re-issuance impossible. Now "one *active* QR per source", so superseded
>    generations accumulate and keep verifying.
> 5. **A failed request must never read as "not found".** The console originally
>    fell back to its sample set on error, which would have told an officer a
>    genuine document was absent from the register. It now reports the failure.
>
> **Outstanding:** resolvers and token cutover for the other 13 document types
> (tracking sheets next — the decode guard is already in place); backfill of
> pre-QR documents; the public portal.

---

## 1. Objective

Make every printed Ministry document verifiable: scan its QR, prove it was issued by KLAES and not altered, resolve it to the file it belongs to, and show who printed it, when, and how many times.

Two things must be true that are not true today:

1. A QR that KLAES did not issue must fail. Today any QR containing a plausible file number passes.
2. The scanner must know *which document* it is looking at, not just which file.

---

## 2. What is in the QR today

Audited across the print paths. There is no single QR format — there are four, and two are broken.

| Document | Location | Payload |
|---|---|---|
| Tracking Sheet | [tracking-sheet.blade.php:253](resources/views/fileindexing/tracking-sheet.blade.php#L253), [print-tracking-sheet.blade.php:205](resources/views/fileindexing/print-tracking-sheet.blade.php#L205) | Raw JSON — `tracking_id`, `file_number`, `file_title`, `plot_number`, `district`, `lga`, `batch_no`, `registry`, `status`, internal `url` |
| Tracking Sheet (batch) | [batch-tracking-sheet.blade.php:189](resources/views/fileindexing/batch-tracking-sheet.blade.php#L189) | URL `/verify-file/{file_number}/{tracking_id}` |
| Tracking Sheet (KANGIS) | [kangis-tracking-sheet.blade.php:99](resources/views/fileindexing/kangis-tracking-sheet.blade.php#L99) | Bare `tracking_id`, literal `'N/A'` when null |
| RofO | [rofo_print.blade.php:763](resources/views/land_rofos/templates/rofo_print.blade.php#L763), [batch_rofo_print.blade.php:193](resources/views/land_rofos/templates/batch_rofo_print.blade.php#L193) | Bare `tracking_id ?: file_number` |

### Defects found

- **`/verify-file/` does not exist.** No such route in `routes/`. Every batch tracking sheet printed to date carries a QR that 404s.
- **`'N/A'` is printed as a scannable QR** when a KANGIS sheet has no tracking ID.
- **The QR images come from a third party.** All of the above call `https://api.qrserver.com/v1/create-qr-code/?data=…`. The payload — including file title, plot, district and LGA on the JSON variant — is sent to an external host in a URL query string on every print, and printing degrades to a broken-image box when the server cannot reach the internet. Given that production cannot reach `login.betasms.com` at all, this host should not be assumed reachable either.
- **Nothing is signed.** Every payload is a plaintext string a forger can reproduce.

### Two tracking-ID formats, and only one has a prefix

| Generator | Format |
|---|---|
| [FileTracker::generateTrackingId()](app/Models/FileTracker.php#L171) | `TRK-{ymdHis}-{RAND4}` |
| [FileIndexController::generateTrackingId()](app/Http/Controllers/FileIndexController.php#L5022) | `ABCD-EFGH2345` — **no prefix**, Crockford-style alphabet |

This kills prefix-based legacy detection. The Q0 decoder must be *"anything that is not a `KLAES-Q1:` token, tried against every known legacy shape"* — not a `TRK-` match. (The verification console I built currently uses prefix matching in `readToken()`; it needs this correction when it is wired to the backend.)

---

## 3. The decision: one global table

**One global QR table. Not one per module.**

The question is really *"what does the scanner know at the moment it decodes?"* — and the answer is **nothing**. The whole point of the token is that the scanner cannot tell what it is holding until after it decrypts. A per-module table requires knowing the module *before* the lookup, so it would force the module name into the QR in plaintext, which re-introduces the leak the token exists to close.

Concretely, per-module tables would mean:

- **Token uniqueness cannot be enforced.** A `UNIQUE` constraint on the token hash only works in one table. Across twelve, two modules can mint colliding tokens and nothing catches it.
- **Every audit question becomes a twelve-way `UNION`.** "How many reprints across the Ministry today", "every scan that failed authentication this week" — these are the questions the system exists to answer.
- **Adding a document type becomes a migration** instead of a row of config.
- **It entrenches the divergence.** Four QR formats already exist precisely because each print path made its own decision. Federating storage guarantees a fifth.

The one real argument for per-module tables — that each module's document has different fields — is satisfied without splitting: **the module tables stay the source of truth for content.** The global table stores only *document identity and audit*, never a copy of the document's fields.

### Departing from the earlier sketch: three tables, not five

The earlier design proposed `documents`, `document_qr_codes`, `document_print_logs`, `document_scan_logs`, `document_types`. Two of those should go:

- **Drop the separate `documents` table.** It duplicates `file_number` / `tracking_id` alongside `document_qr_codes` and the two will drift. Under Approach A (one QR per document, reprints share it) a document instance and its QR are 1:1 — so they are one row. `document_qr_codes` *is* the document-instance registry.
- **Drop the `document_types` table.** Each type needs a resolver *class* regardless (§6). A table listing types that the code must also enumerate is two sources of truth that silently disagree the first time someone inserts a row without writing the resolver. Make it a PHP enum, and let a missing resolver be a deploy-time error rather than a runtime "unknown document type".

---

## 4. Schema

SQL Server syntax — the tables live on `sqlsrv`.

```sql
CREATE TABLE document_qr_codes
(
    id                  BIGINT IDENTITY(1,1) PRIMARY KEY,

    -- what this document is
    document_type       NVARCHAR(40)  NOT NULL,   -- 'ROFO','RDS','COR','ST','TRACKING_SHEET',…
    source_table        NVARCHAR(128) NULL,       -- module table that owns the content
    source_id           BIGINT        NULL,       -- row id in that table

    -- how it reaches a file
    file_indexing_id    BIGINT        NULL,
    file_number         NVARCHAR(255) NULL,       -- SNAPSHOT at issue, cross-check only
    tracking_id         NVARCHAR(255) NULL,       -- SNAPSHOT at issue, cross-check only
    tracking_id_source  NVARCHAR(40)  NULL,       -- 'grouping' | 'commissioning' | 'file_tracker' | 'none'

    -- token identity
    qr_version          SMALLINT      NOT NULL DEFAULT 1,
    key_id              SMALLINT      NOT NULL,   -- which signing key; survives rotation
    token_hash          CHAR(64)      NOT NULL,   -- SHA-256 of the emitted token

    -- 'active' | 'superseded'. 'revoked' is RESERVED, not yet in use — document
    -- revocation is deferred (§11). Do not write it until that work is picked up.
    status              NVARCHAR(20)  NOT NULL DEFAULT 'active',

    issued_at           DATETIME2     NOT NULL DEFAULT SYSDATETIME(),
    issued_by           BIGINT        NULL,
    print_count         INT           NOT NULL DEFAULT 0,
    last_printed_at     DATETIME2     NULL,
    last_printed_by     BIGINT        NULL,

    created_at          DATETIME2     NOT NULL DEFAULT SYSDATETIME(),
    updated_at          DATETIME2     NOT NULL DEFAULT SYSDATETIME()
);

CREATE UNIQUE INDEX ux_dqr_token_hash ON document_qr_codes (token_hash);
CREATE UNIQUE INDEX ux_dqr_source     ON document_qr_codes (document_type, source_id)
    WHERE source_id IS NOT NULL;          -- Approach A: one QR per document instance
CREATE INDEX ix_dqr_file_indexing ON document_qr_codes (file_indexing_id);
CREATE INDEX ix_dqr_tracking      ON document_qr_codes (tracking_id);
```

`file_number` and `tracking_id` are **snapshots taken at issue time**, not truth. Their job is to be compared against what the module tables say *now* — a mismatch is a finding worth surfacing, not an error to paper over.

```sql
CREATE TABLE document_print_logs
(
    id               BIGINT IDENTITY(1,1) PRIMARY KEY,
    document_qr_id   BIGINT        NOT NULL,
    print_number     INT           NOT NULL,   -- 1 = original
    copy_type        NVARCHAR(20)  NOT NULL,   -- 'original' | 'reprint'
    printed_by       BIGINT        NULL,
    printed_at       DATETIME2     NOT NULL DEFAULT SYSDATETIME(),
    reason           NVARCHAR(255) NULL,
    batch_reference  NVARCHAR(100) NULL,       -- e.g. RDS batch RB-2025-041
    ip_address       NVARCHAR(64)  NULL,
    user_agent       NVARCHAR(1000) NULL,
    CONSTRAINT fk_dpl_qr FOREIGN KEY (document_qr_id) REFERENCES document_qr_codes(id)
);
CREATE INDEX ix_dpl_qr ON document_print_logs (document_qr_id, print_number);

CREATE TABLE document_scan_logs
(
    id               BIGINT IDENTITY(1,1) PRIMARY KEY,
    document_qr_id   BIGINT        NULL,       -- NULL when the token never resolved
    qr_version_seen  NVARCHAR(10)  NULL,       -- 'Q1' | 'Q0' | 'REF' | NULL
    raw_payload      NVARCHAR(512) NULL,       -- ONLY on failure — see below
    scanned_at       DATETIME2     NOT NULL DEFAULT SYSDATETIME(),
    scanned_by       BIGINT        NULL,       -- NULL for public/anonymous
    channel          NVARCHAR(40)  NOT NULL,   -- 'qr_scan' | 'manual' | 'public' | 'api'
    ip_address       NVARCHAR(64)  NULL,
    user_agent       NVARCHAR(1000) NULL,
    result           NVARCHAR(30)  NOT NULL,   -- authentic|review|revoked|tampered|notfound
    failure_reason   NVARCHAR(500) NULL
);
CREATE INDEX ix_dsl_qr   ON document_scan_logs (document_qr_id, scanned_at DESC);
CREATE INDEX ix_dsl_when ON document_scan_logs (scanned_at DESC);
```

**Store the raw payload only when verification fails.** A failed scan is evidence and you want the exact bytes. A table of *valid* plaintext tokens, on the other hand, is a forgery kit — anyone with read access could mint working copies of every document ever issued. Valid scans record `document_qr_id` and nothing else identifying.

---

## 5. Token specification

```
KLAES-Q1:<base64url( header ‖ nonce ‖ ciphertext ‖ tag )>
```

| Segment | Bytes | Contents |
|---|---|---|
| header | 2 | `qr_version` (1), `key_id` (1) — also passed as AEAD **additional authenticated data**, so the header cannot be swapped |
| nonce | 12 | random per token |
| ciphertext | 8 | `document_qr_id` (5) ‖ `document_type_code` (1) ‖ `issued_epoch_days` (2) |
| tag | 16 | AES-256-GCM authentication tag |

38 bytes → 51 base64url chars → **60 characters with the prefix**. That is a QR version 3 symbol at ECC level M, which stays scannable printed at 20mm — small enough for the RofO header slot and the tracking-sheet corner.

**Cipher:** `aes-256-gcm` via OpenSSL. Verified available on this stack (PHP 8.2.0, `openssl` loaded, `aes-256-gcm` present in `openssl_get_cipher_methods()`; `sodium` is **not** loaded, so do not reach for `sodium_crypto_aead_*`).

**Key storage:** the master key lives in the server environment/config, **never in a database column**. `key_id` in the row records which key signed it, so rotation does not invalidate previously printed paper — without it, the first rotation kills every QR ever issued.

**Rejected — Laravel `Crypt::encryptString()`:** it is authenticated (AES-256-CBC + HMAC, encrypt-then-MAC), so it would be *safe*. But its output is base64'd JSON carrying iv + mac + value, roughly 200+ characters for this payload, which pushes the symbol to QR version 9 and hurts scan reliability at small print sizes. Raw GCM is the same guarantee in a quarter of the space.

**What the token deliberately does not contain:** the file number, the tracking ID, the holder, or any URL. Everything is resolved server-side from `document_qr_id`.

---

## 6. Resolver architecture

One contract, one implementation per document type, registered in a map:

```php
interface DocumentTypeResolver
{
    public function type(): DocumentType;
    public function identify(int $sourceId): DocumentIdentity;   // file_indexing_id, file_number, tracking_id + source
    public function verify(DocumentQrCode $qr): VerificationChain;
}
```

`VerificationChain` is an ordered list of steps, each `pass | warn | fail | info | skip` — the shape the verification console already renders.

### The ST special case

**ST files have no grouping table. Their tracking ID is auto-generated when the file is commissioned.**

Every other type resolves its tracking ID through a grouping record. The ST resolver must not — for ST, `tracking_id_source = 'commissioning'` and the chain emits:

- `info` — *"Grouping record — not applicable: ST files have no grouping table; the tracking ID is auto-generated at commissioning"*
- `pass` — *"Tracking ID matches commissioning record"*

This has to be explicit in the resolver contract, because the default "no grouping row found" path is a **failure** everywhere else. If ST inherits the generic resolver, every ST document in the Ministry verifies as broken. The `tracking_id_source` column exists so this rule is data, visible in a query, rather than a branch buried in code.

---

## 7. Legacy (Q0) decoding

Printed paper cannot be recalled, so Q0 support is effectively permanent. The decoder is a chain of matchers, tried in order, and it must handle all four shapes found in §2:

| # | Shape | Handling |
|---|---|---|
| 1 | JSON blob | Parse, take `tracking_id` → resolve; ignore the rest (it is unverified claimed data, not evidence) |
| 2 | `/verify-file/{file_number}/{tracking_id}` URL | Extract both segments → resolve |
| 3 | Bare tracking ID | Try **both** generator formats (§7.1) — no prefix assumption, and **never strip the trailing segment** |
| 4 | Literal `N/A` | Resolve to **`unverifiable`**, not `notfound` |

Shape 4 matters more than it looks: a genuine KANGIS tracking sheet whose QR was empty the day it was printed must not be reported to a registry officer as "not in register". It is a KLAES defect, not a suspicious document, and the two need different words on screen.

**Every Q0 result caps at `review`, never `authentic`** — an unsigned payload resolves a record but cannot prove the paper was not altered.

---

## 7.1 Tracking ID grammar — the trailing segment is a registry code

The digits on the end of a File Tracking tracking ID are **not random padding**. They are the **origin registry code**, and both the file-tracking workflow and its QR scanner depend on them.

```
TRK-{ymdHis}-{RAND4}-{REGISTRY}
 │      │        │        └── physical_registries.registry_code — R001…R018, varchar(4)
 │      │        └─────────── 4 random chars
 │      └──────────────────── creation timestamp, yymmddHHiiss
 └─────────────────────────── literal prefix
```

Set at [FileTracker.php:171](app/Models/FileTracker.php#L171), from `origin_registry_code` on the create request ([CreateFileTrackerController.php:200](app/Http/Controllers/CreateFileTrackerController.php#L200), [FileTrackerApiController.php:271](app/Http/Controllers/Api/FileTrackerApiController.php#L271)). Codes were seeded in [2026_05_24_193306_add_registry_code_to_physical_registries_table.php](database/migrations/2026_05_24_193306_add_registry_code_to_physical_registries_table.php) — `R001`–`R018`, covering Land, Deeds, Cadastral, KANGIS, SLTR, ST, DCIV, Survey, SIT and the archives.

Rules that follow:

- **Never normalise, truncate or right-trim a tracking ID.** The segment count is meaningful: four segments carry an origin registry, three do not. Any "cleanup" that drops it destroys routing information.
- **The registry segment is optional.** `generateTrackingId()` takes a nullable code, and several call sites pass none — [FileIndexController.php:425](app/Http/Controllers/FileIndexController.php#L425), [FileCommissioningTrackingService.php:399](app/Services/FileCommissioningTrackingService.php#L399). Parsers must treat a 3-segment ID as valid, not malformed.
- **The other generator has no registry segment at all.** [FileIndexController.php:5022](app/Http/Controllers/FileIndexController.php#L5022) emits `ABCD-EFGH2345` — two segments, no prefix, no registry.

### There are FOUR grammars in production, not two

Checked against live data rather than against the generators in code. Two of these are produced by no generator still in the codebase:

| # | Shape | Example | Where |
|---|---|---|---|
| 1 | `TRK-{ymdHis}-{RAND4}[-{REGISTRY}]` | `TRK-260822135626-5HLK` | `file_tracker` |
| 2 | `TRK-{8}-{5}` | `TRK-CEDEAA2B-B601D` | `mls_file_no`, `file_indexings`, `land_recommendations` |
| 3 | `{4}-{8}` confusable-free, no prefix | `ABCD-EFGH2345` | `FileIndexController` generator |
| 4 | **a bare integer** | `179239` | `land_recommendations` — **348 of 375 rows** |

**Grammar 4 is the weakest thing printed on any Ministry document.** A printed RofO carries `$recommendation->tracking_id`, and for the overwhelming majority of records that is a bare sequential number. Two consequences:

- **The register can be walked by counting.** Scan one RofO, get `179239`, and `179238` / `179240` are other people's live documents.
- **It is trivially forged.** There is nothing to check — any number in range "verifies".

A numeric Q0 payload must therefore never yield `authentic`, only "this number exists in the register". `QrPayloadReader::numericIsEnumerable()` exists to make that cap explicit at the call site.

### Drift: the registry code is stored twice

It lives **inside the tracking ID string** and **in `file_tracker.registry_code`** — and they can disagree. [FileTrackerApiController.php:1149](app/Http/Controllers/Api/FileTrackerApiController.php#L1149) updates the column on its own; the tracking ID string keeps whatever code it was minted with.

Both are correct, for different questions:

| Source | Means |
|---|---|
| Embedded in the tracking ID | The registry the file **originated from**, stamped at creation — immutable history |
| `file_tracker.registry_code` | The origin registry **as currently recorded** — editable |

The resolver reads the **column**, never the string. The string is treated as a historical stamp. Do not "repair" one from the other — a mismatch is a legitimate record of a corrected origin, not corruption.

### The file-tracking QR scanner will break on a Q1 token

This is the one hard integration point, and it fails at the counter if missed.

[quick-actions-js.blade.php:282](resources/views/create_file_tracker_page/partials/quick-actions-js.blade.php#L282) takes the scanned value, trims it, and passes it **verbatim** as a tracking ID:

```
GET /filetracker/get-file-info/{scanned}?by=tracking_id
```

The moment a tracking sheet prints a `KLAES-Q1:…` token, that lookup returns nothing and the "Log to Next Department" workflow stops working for that file.

**The good news:** the scanner does *not* parse the registry code out of the string client-side — it reads `origin_registry_code` off the API response ([:365](resources/views/create_file_tracker_page/partials/quick-actions-js.blade.php#L365)) and auto-selects the registry dropdown. So an opaque token is fine, provided the resolver returns that field.

**Required, in the same phase as the first Q1 tracking-sheet print:** put a decode step in front of that endpoint — if the value matches `KLAES-Q1:`, verify the token, resolve to `document_qr_id` → `tracking_id`, then continue exactly as today. A three-line guard, but it must ship *with* the print change, not after it.

This also settles a design point: **the file-tracking scanner is an operational tool, not the verification portal.** It routes files and logs movement, so it needs the full operational payload — origin registry, current location, handler, page count. The narrow provenance answer of §12.1 governs the *verification* endpoint. Two endpoints, two purposes, one token.

---

## 8. Render QR codes locally

`bacon/bacon-qr-code ^3.0` is **already in `composer.json`** and already used locally with the SVG backend at [PhsAdminController.php:257](app/Http/Controllers/Phs/PhsAdminController.php#L257). No new dependency is needed.

Replace every `api.qrserver.com` URL with a `QrRenderer` service returning an inline SVG or a `data:` URI. This is independent of the token work and should ship first — it removes the external dependency, the data leak, and the internet requirement for printing, on documents whose QR content has not changed yet.

---

## 9. Print and scan audit

**Approach A — one QR per document; reprints share it.** The QR identifies the *document*; `document_print_logs` identifies each *printing event*. Chosen over a new QR per print because a reprint of a CofO is the same legal document, and issuing a fresh token per copy means a holder's original stops matching the register the moment a certified copy is issued.

The boundary: a **reprint** shares the QR and adds a print-log row; a **re-issuance on fresh security paper** mints a new document instance (§12.3). The test is whether a new serial was consumed.

`print_count` on the parent row stays as a denormalised counter for list screens, but the log is the record of truth. Wiring point: the existing per-module `print_count` increments (e.g. [FileIndexController.php:3563](app/Http/Controllers/FileIndexController.php#L3563), [ConsentApplicationController.php:628](app/Http/Controllers/ConsentApplicationController.php#L628)) become calls into the print-log service. Those counters already exist and disagree in shape across modules — this is the opportunity to unify them.

Scanning always writes to `document_scan_logs`, including failures — a forged QR presented at a counter is exactly the event worth having a row for.

---

## 10. Phasing

| Phase | Work | Ships |
|---|---|---|
| **0** | Local QR rendering (bacon); delete the `/verify-file/` dead URL; fix the `'N/A'` sentinel | Independently, no schema |
| **1** | Migration for the three tables; `QrTokenService` (mint/verify); `DocumentType` enum | Schema + service, nothing printed yet |
| **2** | Resolvers for the first three types — RofO, Tracking Sheet, RDS/CoR; verification endpoint; **token decode in front of `/filetracker/get-file-info` (§7.1)**; wire [verification.blade.php](resources/views/information_products/verification.blade.php) off its sample data | First real verifications |
| **3** | Q0 decoder + `unverifiable` result; remaining resolvers (ST — including the §6 rule — SLTR, Recommendation, Commissioning, Confirmation, Bill/Balance) | Full coverage |
| **4** | Public verification portal, rate limiting, anonymous scan logging | External-facing |

Per §12.1 the public portal returns the same narrow provenance answer as the internal endpoint, so Phase 4 is rate limiting, abuse logging and a landing page — not a second response shape.

Phase 2 deliberately ships three types rather than all fourteen — the resolver contract is the risky part of the design, and RDS/CoR exercises the hardest case (two documents, one tracking ID, a parent-child dependency) before it is repeated a dozen times.

**Backfill:** documents already printed cannot gain a Q1 token — the paper exists. Backfilling `document_qr_codes` rows for them is still worth doing in Phase 3 so their print history and file linkage are queryable; they simply carry `qr_version = 0` and never verify above `review`.

---

## 11. Deployment note — the migrations ledger

**The `migrations` ledger lives in MySQL while these tables are created on `sqlsrv`.** The `migrations` table visible on sqlsrv is stale and must not be trusted. Ship, alongside the Laravel migration:

- `database/sql/<date>_document_qr_codes.sql` — the SQL Server DDL above
- `database/sql/<date>_document_qr_codes_ledger.mysql.sql` — the matching MySQL ledger insert

Skipping the ledger file is how a migration ends up marked as run while its DDL never landed — which previously left a 500 in production for about two months.

Also: `.env` is gitignored, so **the QR signing key will simply be absent after a code upload.** Ship a `qr:doctor` artisan command in Phase 1 that reports, in plain language, whether the key is present, which `key_id` is active, and whether `aes-256-gcm` is available — and make token minting fail loudly at print time rather than emitting an unsigned QR.

---

## 12. Decisions

Resolved 2026-08-23.

### 12.1 The question the system answers

**"Is this document legitimate, and was it printed in KLAES?"** — that and nothing more.

This is narrower than the earlier draft assumed, and it simplifies the response everywhere. Verification is a statement about **provenance**, not about title, ownership or entitlement. The answer is essentially binary, plus enough context to identify what was scanned:

| Returned | Withheld |
|---|---|
| Authentic / not authentic | Holder or grantee name |
| Document type | Property details, plot, district |
| Issue date | Consideration, encumbrance, ground rent |
| File number | Anything about the *state* of the title |
| Print count and date of first print | Party names on deeds documents |

Two consequences worth stating plainly:

- **The public portal returns the same narrow answer as the internal one.** There is no tiered disclosure to design, no "authenticated users see more" branch, and therefore no risk of the portal becoming a state-wide land-ownership lookup. The internal console may still show the full record — but it does so from the user's existing module permissions, *not* from the verification response.
- **The verification console currently overshoots.** [verification.blade.php](resources/views/information_products/verification.blade.php) renders a full Holder & Property panel from its sample data. When it is wired to the real endpoint in Phase 2, that panel must be fed from the module record under the operator's normal permissions, not from the verification payload — otherwise the scan response becomes a permission bypass.

### 12.2 Revocation — deferred, not designed away

**Document revocation has not started and is out of scope for this work.** The `status` column reserves `'revoked'` but nothing writes it. Leave a comment at each site where the check would go — the `VerificationChain` builder and the `status` column — so the next person picking it up finds the seam rather than re-deriving it.

**This is not the same thing as title revocation, and the two must not be conflated:**

| | Deferred | Already real |
|---|---|---|
| **Document/QR revocation** — this printed copy is withdrawn (lost, superseded in error, recalled) | ✅ deferred | |
| **Title revocation** — the land title itself was revoked by the Ministry | | ✅ exists in the register today |

Title status is register data that already exists and is unrelated to whether the paper is genuine. But under §12.1 it is **not part of the verification response** — a scanner is told the document is authentic; what the title's current status is comes from the register through the normal screens. The existing `revoked` verdict in the console (sample `KN/OP/2021/00455`, banner "Title Revoked") is the *title* case and stays as-is; no document-revocation verdict should be added yet.

### 12.3 Re-issuance creates a new document instance

**Confirmed.** A re-issuance on fresh security paper is a new document, not a reprint:

- Insert a **new `document_qr_codes` row** with its own token.
- Mark the previous row `status = 'superseded'` and record which row replaced it (`superseded_by_id BIGINT NULL`, FK to `document_qr_codes`).
- **Both remain resolvable.** The superseded token must keep verifying and report *"authentic, but superseded by a re-issuance dated …"*. Making the old token stop resolving is the one outcome to avoid: a dead QR is indistinguishable from a forgery, and someone holding the earlier paper in good faith would be told their genuine document is fake.

This is the boundary against Approach A in §9: a **reprint** (certified copy, damaged original) shares the QR and adds a print-log row; a **re-issuance** mints a new one. The distinction is whether new security paper with a new serial was consumed.

Add to the schema in §4:

```sql
superseded_by_id  BIGINT NULL,   -- set when a re-issuance replaces this document
CONSTRAINT fk_dqr_superseded FOREIGN KEY (superseded_by_id) REFERENCES document_qr_codes(id)
```
