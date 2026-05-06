BEGIN TRY
    BEGIN TRANSACTION;

    -- PRA must carry the new commissioned file number too
    UPDATE [Klas].[dbo].[pra]
    SET
        [mlsFNo] = 'RES-2026-1559',
        [fileno] = 'RES-2026-1559',
        [updated_at] = GETDATE()
    WHERE [id] = 70218;

    -- Link commissioned row back to PRA (if column exists)
    IF COL_LENGTH('Klas.dbo.mls_file_no', 'source_pra_id') IS NOT NULL
    BEGIN
        UPDATE [Klas].[dbo].[mls_file_no]
        SET
            [source_pra_id] = 70218,
            [updated_at] = GETDATE()
        WHERE [id] = 11895;
    END

    -- Optional: if your flow needs a PRA source marker on mls_file_no
    IF COL_LENGTH('Klas.dbo.mls_file_no', 'source') IS NOT NULL
    BEGIN
        UPDATE [Klas].[dbo].[mls_file_no]
        SET [source] = COALESCE([source], 'PRA')
        WHERE [id] = 11895;
    END

    -- Verify
    SELECT [id], [prop_id], [temp_fileno], [fileno], [mlsFNo], [transaction_type]
    FROM [Klas].[dbo].[pra]
    WHERE [id] = 70218;

    SELECT [id], [full_file_number], [source], [source_pra_id]
    FROM [Klas].[dbo].[mls_file_no]
    WHERE [id] = 11895;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;

delete from [Klas].[dbo].[mls_file_no] where  full_file_number  in ('AG-2026-1', 'AG-2026-2', 'AG-2026-3');




