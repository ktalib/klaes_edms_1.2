IF OBJECT_ID('dbo.tp_no_lookup', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tp_no_lookup (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        tp_no NVARCHAR(100) NOT NULL UNIQUE,
        source_sn INT NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    );
END;

-- Insert TP numbers from docs/data/TPNo LuT.csv (deduplicated case-insensitively)
IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/256F'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/256F', 1, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/DTF/5A'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/DTF/5A', 2, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/358D'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/358D', 3, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/334E'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/334E', 4, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TPMLPP/UC/DTF/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TPMLPP/UC/DTF/17', 5, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KAS/215M'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KAS/215M', 6, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/DTF/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/DTF/17', 7, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/338F'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/338F', 8, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/3070'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/3070', 9, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/215M'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/215M', 10, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/507D'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/507D', 11, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/307D'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/307D', 12, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/UC/DFT/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/UC/DFT/17', 13, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/215J'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/215J', 14, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/358C'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/358C', 15, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/3010'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/3010', 16, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/UGG 215N'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/UGG 215N', 17, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPPKBT/2569'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPPKBT/2569', 18, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/DTF/16B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/DTF/16B', 19, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/UDB/172'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/UDB/172', 20, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KAS/236A'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KAS/236A', 21, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/358A'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/358A', 22, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/TF/5A'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/TF/5A', 23, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/381'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/381', 24, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/358B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/358B', 25, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/256F'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/256F', 27, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/256G'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/256G', 28, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/DTF/16 B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/DTF/16 B', 29, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/UGG/218N'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/UGG/218N', 30, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/215K'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/215K', 31, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/DKD/14'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/DKD/14', 32, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MCPP/DKD/001'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MCPP/DKD/001', 33, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/358E'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/358E', 34, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/92/02'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/92/02', 35, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/DKD/001'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/DKD/001', 36, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/C/DTF/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/C/DTF/17', 37, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/K/328C'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/K/328C', 38, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/382B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/382B', 39, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/UC/DTF/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/UC/DTF/17', 40, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/333A'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/333A', 41, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/381'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/381', 42, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'KAN/144'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'KAN/144', 43, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/277'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/277', 44, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/381'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/381', 46, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/2151'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/2151', 47, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/K/334B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/K/334B', 48, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/2569'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/2569', 49, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/334E'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/334E', 50, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/GZ/02'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/GZ/02', 52, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/UC/DTF/16'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/UC/DTF/16', 53, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MCPP/KBT/3070'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MCPP/KBT/3070', 54, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/307'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/307', 55, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT//2569'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT//2569', 56, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MPPL/UGG/215N'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MPPL/UGG/215N', 57, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/215M'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/215M', 58, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/334B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/334B', 59, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/U'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/U', 61, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/UGG/215N'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/UGG/215N', 62, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/UGG//215N'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/UGG//215N', 63, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPPDKD/001'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPPDKD/001', 65, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/DKD001'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/DKD001', 66, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/2560'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/2560', 67, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TPKN/256F'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TPKN/256F', 69, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/334E'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/334E', 70, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPD/UC/TIF/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPD/UC/TIF/17', 71, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT3070'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT3070', 72, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/328B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/328B', 73, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/UC/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/UC/17', 74, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT307D'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT307D', 78, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/256B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/256B', 79, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MB/9B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MB/9B', 80, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP//KBT/307D'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP//KBT/307D', 81, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/01/DTF/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/01/DTF/17', 82, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/215M'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/215M', 83, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/DKD/KBT/256G'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/DKD/KBT/256G', 86, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KAS/215B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KAS/215B', 87, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UD/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UD/17', 89, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/382'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/382', 90, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/DTP/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/DTP/17', 91, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MPLL/KBT/256G'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MPLL/KBT/256G', 92, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/256F'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/256F', 93, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/K/377'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/K/377', 94, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MCPP/UGG/215N'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MCPP/UGG/215N', 95, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/UDB/192A'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/UDB/192A', 96, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/338B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/338B', 97, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/277'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/277', 98, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/381'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/381', 99, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT256G'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT256G', 100, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/256G'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/256G', 101, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/TF/5B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/TF/5B', 102, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/5B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/5B', 103, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/215G'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/215G', 106, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/UC/DTF/17'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/UC/DTF/17', 108, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KAS/246'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KAS/246', 109, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/381Z'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/381Z', 110, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/UC/DTF/16B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/UC/DTF/16B', 111, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT/215N'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT/215N', 112, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/UC/DTF/16'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/UC/DTF/16', 113, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KN/256A'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KN/256A', 115, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/KBT2569'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/KBT2569', 116, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUP/307'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUP/307', 117, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/KNUPDA/UC/DTF/16B'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/KNUPDA/UC/DTF/16B', 118, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'TP/MLPP/UGG'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'TP/MLPP/UGG', 119, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tp_no_lookup WHERE UPPER(tp_no) = UPPER(N'Other'))
BEGIN
    INSERT INTO dbo.tp_no_lookup (tp_no, source_sn, created_at, updated_at)
    VALUES (N'Other', 120, GETDATE(), GETDATE());
END;


