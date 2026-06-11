# Token Topup & Wallet System - Enhancement Prompt

## Overview
Transform the current "Purchase Tokens" flow into a modern **Token Topup & Wallet** system that allows organizations to purchase additional tokens beyond their initial subscription packages.

---

## Features to Implement

### 1. User Side (Organization Dashboard)

#### 1.1 Token Wallet Display
- **Location**: Organization Dashboard > Wallet Card
- **Display Elements**:
  - Current Token Balance (prominent display with icon)
  - Total Tokens Used (cumulative)
  - Active Package name and expiry date
  - Last 5 transactions (mini ledger)
  - "Top Up" button (CTA)

#### 1.2 Token Topup Modal/Page
- **Trigger**: Click "Top Up" button
- **Form Elements**:
  - **Bundle Selection**:
    - Display available bundles (Starter, Professional, Enterprise)
    - Show tokens per bundle and price per bundle
    - Example: "1 Bundle = 1,000 Tokens @ ₦50,000"
  - **Quantity Input**:
    - Numeric input field (minimum: 1)
    - Real-time total calculation: (Bundles × Price Per Bundle)
    - Display total amount to be paid
  - **Action Buttons**:
    - "Proceed to Payment" (primary CTA)
    - "Cancel"

#### 1.3 Payment Gateway
- **Redirect Flow**: After "Proceed to Payment"
  - Generate a token topup transaction (pending status)
  - Redirect to payment gateway (online payment only)
  - Reference number: `TOPUP/YYYYMMDD/RANDOM6CHARS`

#### 1.4 Payment Confirmation
- **Post-Payment Handling**:
  - Redirect back to dashboard after payment
  - Display success message
  - Tokens credited to wallet
  - Transaction recorded with status: "completed"

#### 1.5 Wallet Ledger/History
- **Location**: Dashboard > Token History Tab
- **Display Columns**:
  - Transaction Type (Topup, Search Used, Adjustment)
  - Tokens (+ for credit, - for usage)
  - Date & Time
  - Reference Number
  - Status (Completed, Pending)
  - Balance After
- **Filters**: 
  - Date Range
  - Transaction Type
  - Status

---

### 2. Admin Side (System Admin Dashboard)

#### 2.1 PHS Subscriptions & Wallet Page (Updated)
- **Page Name**: PHS — Subscriptions & Token Wallet
- **Replace**: Current "PHS — Subscriptions" page
- **Display Mode**: Tabbed interface
  - **Tab 1: Subscriptions** (existing token package purchases)
  - **Tab 2: Token Topups** (new topups - this session)
  - **Tab 3: Organization Wallets** (all org balances)

#### 2.2 Token Topups Tab
- **Table Columns**:
  - Organization Name
  - Bundle Count (how many bundles purchased)
  - Total Amount (₦)
  - Date Requested
  - Payment Method (Online, Invoice)
  - Payment Status (Completed, Pending, Failed)
  - Token Balance Update (did tokens get credited?)
  - Actions (View, Approve, Verify Payment)

- **Actions Available**:
  - **View**: See full transaction details
  - **Verify Payment**: Check if payment was received (only for pending online payments)
  - **Approve**: Credit tokens to wallet (for successful payments)
  - **Cancel**: Reject transaction if needed

#### 2.3 Organization Wallets Tab
- **Table Columns**:
  - Organization Name
  - Current Token Balance
  - Total Tokens Ever Allocated
  - Total Tokens Used
  - Last Topup Date
  - Status (Active, Suspended)
  - Actions (View Ledger, Manual Adjustment, View Details)

- **Actions Available**:
  - **View Ledger**: Complete transaction history for this organization
  - **Manual Adjustment**: Staff can manually add/deduct tokens
  - **View Details**: See full organization profile

#### 2.4 Wallet Ledger View (Per Organization)
- **Columns**:
  - Transaction Date
  - Transaction Type (Subscription Purchase, Topup, Search Usage, Manual Adjustment)
  - Tokens Changed (+ or -)
  - Reason/Reference
  - Balance After
  - Processed By (username if manual)
  - Status

---

### 3. Organization Dashboard Update

#### 3.1 New Wallet Widget
- **Location**: Main Organization Dashboard
- **Display**:
  ```
  ┌─────────────────────────────────┐
  │  💳 Token Wallet                │
  ├─────────────────────────────────┤
  │  Current Balance:  5,250 tokens  │
  │  Package:         Professional  │
  │  Expires:         2025-12-31    │
  │                                 │
  │  [Top Up] [View Ledger]        │
  └─────────────────────────────────┘
  ```

#### 3.2 Recent Transactions Section
- **Show Last 10 Transactions**:
  - Type (icon: up arrow for topup, down for usage)
  - Description
  - Tokens (color-coded: green for +, red for -)
  - Date
  - Link to full ledger

---

## Database Structure

### 1. Updated phs_token_transactions Table
Add columns (if not already present):
```sql
- topup_bundle_count (INT) -- Number of bundles purchased
- topup_unit_price (DECIMAL) -- Price per bundle at time of purchase
- payment_gateway_reference (VARCHAR) -- External payment provider reference
- payment_confirmed_at (DATETIME) -- When payment was actually confirmed
- credited_at (DATETIME) -- When tokens were added to wallet
```

### 2. New Table: phs_token_wallet_ledger (Optional - for detailed tracking)
```sql
- id (INT PK)
- phs_institution_id (INT FK)
- transaction_type (VARCHAR) -- topup, search_usage, adjustment, refund
- tokens_changed (INT) -- positive or negative
- balance_before (INT)
- balance_after (INT)
- reference_id (VARCHAR) -- link to related transaction
- reason (TEXT)
- created_by (INT FK to users)
- created_at (DATETIME)
- notes (TEXT)
```

---

## API Endpoints Needed

### 1. User/Organization APIs

```
POST /phs/api/topup/calculate
  Input: bundle_count, package_slug
  Output: total_amount, tokens_to_receive, bundle_price

POST /phs/api/topup/request
  Input: bundle_count, package_slug, payment_method
  Output: transaction_id, payment_redirect_url (or invoice)

GET /phs/api/wallet/balance
  Output: current_balance, last_update

GET /phs/api/wallet/ledger
  Query: limit, page, type_filter, date_range
  Output: transaction list

GET /phs/api/wallet/stats
  Output: total_tokens_used, total_topups, current_balance, trend
```

### 2. Admin APIs

```
POST /system-admin/phs/topup/verify-payment/{txnId}
  Verify that online payment was received

POST /system-admin/phs/topup/approve/{txnId}
  Credit tokens to wallet

POST /system-admin/phs/topup/reject/{txnId}
  Reject transaction and notify organization

POST /system-admin/phs/wallet/manual-adjustment
  Input: organization_id, tokens_amount (+ or -), reason
  Output: new_balance, transaction_id

GET /system-admin/phs/wallets/organization/{orgId}/ledger
  Get complete wallet history for one organization
```

---

## UI/UX Flow Diagrams

### User Flow: Token Topup
```
1. Organization Dashboard
   ↓ (Click "Top Up")
2. Token Topup Modal
   - Select Bundle quantity
   - See total price
   ↓ (Click "Proceed to Payment")
3. Payment Gateway Page
   - Process online payment
   ↓ (Payment Success)
4. Redirect to Dashboard
   - Show "Tokens Added! Your balance is now X"
   - Update wallet display
   - Show new transaction in ledger
```

### Admin Flow: Verify & Credit
```
1. Admin Dashboard > Token Topups Tab
   ↓ (See pending topup)
2. Click "Verify Payment"
   - Check payment gateway confirmation
   - Confirm payment received
   ↓ (Click "Approve & Credit")
3. System:
   - Updates phs_token_transactions status → completed
   - Updates phs_institutions.token_balance += tokens
   - Creates ledger entry
   - Sends email to organization
4. Topup moves to "Completed" section
```

---

## Key Considerations

### 1. Token Calculation
- Bundle = fixed unit (e.g., Starter Bundle = 1,000 tokens)
- Price per bundle is dynamic (can be configured in PhsTokenPackage)
- Formula: `total_tokens = bundle_count × tokens_per_bundle`
- Formula: `total_price = bundle_count × price_per_bundle`

### 2. Payment Status Flow
```
pending → processing → completed (or failed)
  ↓
  └─→ If completed: credit tokens
  └─→ If failed: show error, allow retry
```

### 3. Transaction Types to Support
- `subscription_purchase` - Initial package (one-time)
- `topup` - Additional tokens via topup
- `search_usage` - Tokens consumed by searches
- `refund` - Refunded tokens
- `manual_adjustment` - Admin manual add/subtract

### 4. Security & Audit
- All token transactions must be logged
- Manual adjustments require admin approval
- Staff who made adjustment logged in ledger
- Email notifications for significant transactions
- Audit trail for compliance

### 5. Edge Cases to Handle
- Payment retry if gateway fails
- Prevent double-crediting if webhook called twice
- Prevent topup if organization is suspended
- Show warning if balance is low (< 100 tokens)
- Limit topup quantity to reasonable max (e.g., 10 bundles max per transaction)

---

## Implementation Priority

### Phase 1 (Critical)
- [ ] Token Topup modal on user dashboard
- [ ] Payment gateway integration for topup
- [ ] Token crediting after payment
- [ ] Basic wallet ledger display

### Phase 2 (Important)
- [ ] Admin verification & approval workflow
- [ ] Wallet ledger page (detailed)
- [ ] Organization wallet dashboard widget
- [ ] Transaction email notifications

### Phase 3 (Enhancement)
- [ ] Manual adjustment feature
- [ ] Advanced analytics/trends
- [ ] Scheduled topup reminders
- [ ] Bulk wallet management

---

## Files to Modify/Create

### Controllers
- [ ] `PhsTokenController::requestTopup()` - Handle topup request
- [ ] `PhsAdminController::verifyTopup()` - Verify payment
- [ ] `PhsAdminController::approveTopup()` - Credit tokens
- [ ] `PhsAdminController::walletLedger()` - View org ledger

### Views
- [ ] `phs/dashboard/wallet-widget.blade.php` - New wallet card
- [ ] `phs/topup/modal.blade.php` - Topup modal
- [ ] `phs/wallet/ledger.blade.php` - User wallet history
- [ ] `system-admin/phs/subscriptions-wallets.blade.php` - Updated admin page
- [ ] `system-admin/phs/organization-wallet-ledger.blade.php` - Org ledger detail

### Models
- [ ] Add methods to `PhsInstitution` for wallet operations
- [ ] Update `PhsTokenTransaction` if needed
- [ ] Create `PhsTokenWalletLedger` model (if new table)

### API Routes
- [ ] Add new PHS API routes for wallet operations
- [ ] Add admin routes for wallet management

---

## Success Criteria

✅ Users can purchase tokens in bundles with clear pricing  
✅ Token balance updates immediately after payment  
✅ Admin can verify and approve topups  
✅ Organization dashboard shows wallet balance  
✅ Complete transaction history visible to users  
✅ Admin can manually adjust tokens when needed  
✅ All transactions audited and logged  
✅ Notifications sent for all significant changes  
✅ No double-crediting of tokens  
✅ Suspended organizations cannot topup  

---

## Notes for Development

1. **Reuse Payment Gateway**: Use same payment provider as subscription flow
2. **Bundle Configuration**: Make bundles configurable per package (not hardcoded)
3. **Email Templates**: Create new email template for topup confirmation & admin notifications
4. **Error Handling**: Clear messages for failed topups, payment errors, etc.
5. **Performance**: Consider caching wallet balance in Redis for dashboard speed
6. **Mobile Friendly**: Ensure topup flow works on mobile devices

---

This prompt provides a complete specification for implementing the token topup and wallet feature set.
