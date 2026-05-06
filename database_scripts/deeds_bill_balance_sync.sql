/*
Backfills billing rows for every record in deeds_bill_balances_metadata that
does not already have a linked billing entry. Aligns with the controller logic
that writes to billing (source = 'DEEDS_BILL_BALANCE').
*/
-- SQL Server version of the migration
CREATE TABLE [dbo].[deeds_bill_balances_metadata] (
    [id] BIGINT IDENTITY(1,1) PRIMARY KEY,
    [reference] NVARCHAR(255) NOT NULL UNIQUE,
    [file_number] NVARCHAR(255) NOT NULL,
    [applicant_name] NVARCHAR(255) NOT NULL,
    [applicant_address] NVARCHAR(255) NULL,
    [location_station] NVARCHAR(255) NULL,
    [district] NVARCHAR(255) NULL,
    [amount] DECIMAL(18, 2) NULL,
    [status] NVARCHAR(40) NOT NULL DEFAULT 'draft',
    [notes] NVARCHAR(MAX) NULL,
    [prepared_by] NVARCHAR(255) NULL,
    [prepared_at] DATETIME2 NULL,
    [billing_id] BIGINT NULL,
    [created_at] DATETIME2 NULL,
    [updated_at] DATETIME2 NULL
);

-- Index for faster queries on reference
CREATE UNIQUE INDEX [IX_deeds_bill_balances_metadata_reference]
    ON [dbo].[deeds_bill_balances_metadata]([reference]);

-- Index for status filtering
CREATE INDEX [IX_deeds_bill_balances_metadata_status]
    ON [dbo].[deeds_bill_balances_metadata]([status]);



-------------------------------------------------------
SET NOCOUNT ON;

DECLARE @now DATETIME = GETDATE();

IF OBJECT_ID('tempdb..#MissingBilling') IS NOT NULL
    DROP TABLE #MissingBilling;

SELECT
    m.id AS metadata_id,
    ISNULL(NULLIF(m.file_number, ''), ISNULL(NULLIF(m.reference, ''), CONCAT('DEEDS-BAL-', m.id))) AS ref_id,
    m.reference,
    ISNULL(m.amount, 0) AS amount,
    COALESCE(m.prepared_at, @now) AS prepared_at,
    CASE LOWER(ISNULL(m.status, ''))
        WHEN 'approved' THEN 'Paid'
        WHEN 'archived' THEN 'Closed'
        ELSE 'Pending'
    END AS payment_status
INTO #MissingBilling
FROM deeds_bill_balances_metadata m WITH (NOLOCK)
WHERE NOT EXISTS (
    SELECT 1
    FROM billing b WITH (NOLOCK)
    WHERE (b.id = m.billing_id)
       OR (b.source = 'DEEDS_BILL_BALANCE' AND b.source_id = m.id)
);

IF EXISTS (SELECT 1 FROM #MissingBilling)
BEGIN
    INSERT INTO billing (
        ref_id,
        source,
        source_id,
        Penalty_Fees,
        Payment_Status,
        bill_balance_reciept,
        bill_balance_date,
        Site_Plan_Fee,
        survey_fee,
        Processing_Fee,
        Betterment_Charges,
        Unit_Application_Fees,
        Land_Use_Charge,
        property_value,
        betterment_rate,
        Betterment_receipt,
        receipt_date,
        created_at,
        updated_at
    )
    SELECT
        mb.ref_id,
        'DEEDS_BILL_BALANCE',
        mb.metadata_id,
        mb.amount,
        mb.payment_status,
        ISNULL(mb.reference, CONCAT('DEEDS-BAL-', mb.metadata_id)),
        mb.prepared_at,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        NULL,
        NULL,
        @now,
        @now
    FROM #MissingBilling mb;
END;

;WITH BillingMatches AS (
    SELECT b.id, b.source_id
    FROM billing b WITH (NOLOCK)
    WHERE b.source = 'DEEDS_BILL_BALANCE'
      AND b.source_id IS NOT NULL
)
UPDATE m
SET m.billing_id = bm.id
FROM deeds_bill_balances_metadata m
JOIN BillingMatches bm ON bm.source_id = m.id
WHERE m.billing_id IS NULL OR m.billing_id <> bm.id;

DROP TABLE IF EXISTS #MissingBilling;
