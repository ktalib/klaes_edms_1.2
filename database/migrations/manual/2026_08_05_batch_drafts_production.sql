/* ===========================================================================
   Plot Subdivision batch capture — autosaved drafts
   Target: SQL Server (the KLAES `sqlsrv` connection)

   Adds one table. Nothing existing is altered, and no data is read or written,
   so this is safe to run on a live database during working hours.

   Re-runnable: every statement is guarded, so running it twice does nothing
   the second time.

   Covers both migrations:
     2026_08_05_090000_create_land_recommendation_batch_drafts_table
     2026_08_05_140000_add_payload_previous_to_batch_drafts_table

   NOTE ON THE LEDGER: this project keeps Laravel's `migrations` table on the
   MySQL connection, not on SQL Server, so it is not touched here. See the note
   at the bottom.
   =========================================================================== */

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   1. The drafts table.

   A batch capture is written here every few seconds while it is being keyed,
   so a session timeout, a 419, or a closed tab costs nothing. `payload` holds
   the whole capture as JSON (common fields plus every child row, ticked or
   not), which is why it is nvarchar(max) — a 100+ child batch runs to
   hundreds of KB, well past the 4000-character default.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.land_recommendation_batch_drafts', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.land_recommendation_batch_drafts (
        id                      bigint IDENTITY(1,1) NOT NULL,

        -- Minted by the browser when a capture starts; the only key the
        -- autosave knows, since the row id does not exist until the first save.
        draft_key               nvarchar(40)   NOT NULL,

        -- Drafts are private to the officer who keyed them: a half-filled batch
        -- is not a shared work item, and two people resuming the same draft
        -- would silently overwrite each other.
        user_id                 bigint         NULL,

        application_type        nvarchar(60)   NULL,
        mother_file_no          nvarchar(100)  NULL,

        -- Denormalised off the payload so the "resume a draft" list can show
        -- "MLKN 1234 — 96 of 118 children" without decoding every payload.
        children_total          int            NOT NULL CONSTRAINT DF_lr_batch_drafts_children_total    DEFAULT (0),
        children_selected       int            NOT NULL CONSTRAINT DF_lr_batch_drafts_children_selected DEFAULT (0),

        payload                 nvarchar(max)  NULL,

        -- open      — still being keyed, offered for resume
        -- submitted — the batch was saved; kept for the audit trail
        -- discarded — the user threw it away
        status                  nvarchar(20)   NOT NULL CONSTRAINT DF_lr_batch_drafts_status DEFAULT ('open'),

        -- Set when a draft becomes real recommendations, so a draft can be
        -- traced to the batch it produced.
        rofo_batch_id           nvarchar(60)   NULL,

        -- Separate from updated_at: the moment the BROWSER last had its work
        -- accepted, which is what the "Saved 12:04" badge reports.
        last_saved_at           datetime       NULL,

        created_at              datetime       NULL,
        updated_at              datetime       NULL,

        -- One step of undo. Autosave overwrites the draft every few seconds, so
        -- without this any accident that empties the table on screen is written
        -- over the good copy within one debounce. Only a save that LOSES rows
        -- banks a rollback point.
        payload_previous        nvarchar(max)  NULL,
        previous_children_total int            NULL,
        previous_saved_at       datetime       NULL,

        CONSTRAINT PK_land_recommendation_batch_drafts PRIMARY KEY CLUSTERED (id)
    );

    PRINT 'Created table land_recommendation_batch_drafts.';
END
ELSE
    PRINT 'Table land_recommendation_batch_drafts already exists — skipped.';
GO

/* ---------------------------------------------------------------------------
   2. Columns, for a database that already has the table from the first
      migration but not the rollback columns from the second.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.land_recommendation_batch_drafts', 'payload_previous') IS NULL
BEGIN
    ALTER TABLE dbo.land_recommendation_batch_drafts ADD payload_previous nvarchar(max) NULL;
    PRINT 'Added column payload_previous.';
END
GO

IF COL_LENGTH('dbo.land_recommendation_batch_drafts', 'previous_children_total') IS NULL
BEGIN
    ALTER TABLE dbo.land_recommendation_batch_drafts ADD previous_children_total int NULL;
    PRINT 'Added column previous_children_total.';
END
GO

IF COL_LENGTH('dbo.land_recommendation_batch_drafts', 'previous_saved_at') IS NULL
BEGIN
    ALTER TABLE dbo.land_recommendation_batch_drafts ADD previous_saved_at datetime NULL;
    PRINT 'Added column previous_saved_at.';
END
GO

/* ---------------------------------------------------------------------------
   3. Indexes. The names match what Laravel's migration creates, so a later
      `php artisan migrate:rollback` still finds them.
   --------------------------------------------------------------------------- */

-- The autosave upserts on draft_key on every save, so this one is load-bearing
-- rather than merely a constraint.
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'land_recommendation_batch_drafts_draft_key_unique'
                 AND object_id = OBJECT_ID('dbo.land_recommendation_batch_drafts'))
BEGIN
    CREATE UNIQUE INDEX land_recommendation_batch_drafts_draft_key_unique
        ON dbo.land_recommendation_batch_drafts (draft_key);
    PRINT 'Created index ..._draft_key_unique.';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'land_recommendation_batch_drafts_user_id_index'
                 AND object_id = OBJECT_ID('dbo.land_recommendation_batch_drafts'))
BEGIN
    CREATE INDEX land_recommendation_batch_drafts_user_id_index
        ON dbo.land_recommendation_batch_drafts (user_id);
    PRINT 'Created index ..._user_id_index.';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'land_recommendation_batch_drafts_mother_file_no_index'
                 AND object_id = OBJECT_ID('dbo.land_recommendation_batch_drafts'))
BEGIN
    CREATE INDEX land_recommendation_batch_drafts_mother_file_no_index
        ON dbo.land_recommendation_batch_drafts (mother_file_no);
    PRINT 'Created index ..._mother_file_no_index.';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'land_recommendation_batch_drafts_status_index'
                 AND object_id = OBJECT_ID('dbo.land_recommendation_batch_drafts'))
BEGIN
    CREATE INDEX land_recommendation_batch_drafts_status_index
        ON dbo.land_recommendation_batch_drafts (status);
    PRINT 'Created index ..._status_index.';
END
GO

-- The resume list: this user's open drafts, newest first.
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'idx_lr_batch_drafts_user_status'
                 AND object_id = OBJECT_ID('dbo.land_recommendation_batch_drafts'))
BEGIN
    CREATE INDEX idx_lr_batch_drafts_user_status
        ON dbo.land_recommendation_batch_drafts (user_id, status, last_saved_at);
    PRINT 'Created index idx_lr_batch_drafts_user_status.';
END
GO

/* ---------------------------------------------------------------------------
   4. Verification — run this after the script and check the output.
      Expect: 16 columns and 6 indexes (5 above plus the primary key).
   --------------------------------------------------------------------------- */
SELECT
    (SELECT COUNT(*) FROM sys.columns
      WHERE object_id = OBJECT_ID('dbo.land_recommendation_batch_drafts')) AS column_count,
    (SELECT COUNT(*) FROM sys.indexes
      WHERE object_id = OBJECT_ID('dbo.land_recommendation_batch_drafts')
        AND name IS NOT NULL)                                              AS index_count;
GO

/* ===========================================================================
   AFTERWARDS — the migration ledger

   This project stores Laravel's `migrations` table on the MySQL connection,
   so the two rows below cannot be inserted by this SQL Server script. Without
   them, a future `php artisan migrate` will try these migrations again — which
   is harmless, because both are guarded by hasTable()/hasColumn() and will do
   nothing, but it does leave the ledger untidy.

   Either run this against the MySQL database:

     INSERT INTO migrations (migration, batch) VALUES
       ('2026_08_05_090000_create_land_recommendation_batch_drafts_table',
        (SELECT * FROM (SELECT MAX(batch) + 1 FROM migrations) AS b)),
       ('2026_08_05_140000_add_payload_previous_to_batch_drafts_table',
        (SELECT * FROM (SELECT MAX(batch) FROM migrations) AS b));

   ...or simply run `php artisan migrate` on production after this script: both
   migrations will find their table and columns already present, do nothing,
   and record themselves in the ledger.

   NO OTHER DATABASE CHANGES are needed for this release. The Batches tab, the
   mother-file picker counts, the completion-time field and the batch capture
   layout are all query- and display-level changes against existing columns.
   =========================================================================== */
