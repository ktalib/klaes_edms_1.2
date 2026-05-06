CREATE TABLE IF NOT EXISTS CoverType (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(100) NOT NULL,
    Code NVARCHAR(10) NULL,
    Created_Date DATETIME DEFAULT GETDATE(),
    Updated_Date DATETIME DEFAULT GETDATE()
);

-- Insert default cover types if they don't exist
IF NOT EXISTS (SELECT 1 FROM CoverType WHERE Name = 'Front Cover')
    INSERT INTO CoverType (Name, Code) VALUES ('Front Cover', 'FC');

IF NOT EXISTS (SELECT 1 FROM CoverType WHERE Name = 'Back Cover')
    INSERT INTO CoverType (Name, Code) VALUES ('Back Cover', 'BC');

CREATE TABLE IF NOT EXISTS PageType (
    id INT IDENTITY(1,1) PRIMARY KEY,
    PageType NVARCHAR(100) NOT NULL,
    Code NVARCHAR(10) NULL,
    Created_Date DATETIME DEFAULT GETDATE(),
    Updated_Date DATETIME DEFAULT GETDATE()
);

-- Insert default page types if they don't exist
IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'File Cover')
    INSERT INTO PageType (PageType, Code) VALUES ('File Cover', 'FC');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Application')
    INSERT INTO PageType (PageType, Code) VALUES ('Application', 'APP');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Bill Notice')
    INSERT INTO PageType (PageType, Code) VALUES ('Bill Notice', 'BN');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Correspondence')
    INSERT INTO PageType (PageType, Code) VALUES ('Correspondence', 'COR');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Land Title')
    INSERT INTO PageType (PageType, Code) VALUES ('Land Title', 'LT');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Legal')
    INSERT INTO PageType (PageType, Code) VALUES ('Legal', 'LEG');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Payment Evidence')
    INSERT INTO PageType (PageType, Code) VALUES ('Payment Evidence', 'PE');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Report')
    INSERT INTO PageType (PageType, Code) VALUES ('Report', 'REP');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Survey')
    INSERT INTO PageType (PageType, Code) VALUES ('Survey', 'SUR');

IF NOT EXISTS (SELECT 1 FROM PageType WHERE PageType = 'Miscellaneous')
    INSERT INTO PageType (PageType, Code) VALUES ('Miscellaneous', 'MISC');

CREATE TABLE IF NOT EXISTS PageSubType (
    id INT IDENTITY(1,1) PRIMARY KEY,
    PageTypeId INT NOT NULL,
    PageSubType NVARCHAR(100) NOT NULL,
    Code NVARCHAR(10) NULL,
    Created_Date DATETIME DEFAULT GETDATE(),
    Updated_Date DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (PageTypeId) REFERENCES PageType(id)
);

-- Insert default page subtypes if they don't exist
-- File Cover subtypes
DECLARE @FileCovertTypeId INT = (SELECT id FROM PageType WHERE PageType = 'File Cover');
IF @FileCovertTypeId IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @FileCovertTypeId AND PageSubType = 'New File Cover')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@FileCovertTypeId, 'New File Cover', 'NFC');
    
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @FileCovertTypeId AND PageSubType = 'Old File Cover')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@FileCovertTypeId, 'Old File Cover', 'OFC');
END

-- Application subtypes
DECLARE @ApplicationTypeId INT = (SELECT id FROM PageType WHERE PageType = 'Application');
IF @ApplicationTypeId IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @ApplicationTypeId AND PageSubType = 'Certificate of Occupancy')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@ApplicationTypeId, 'Certificate of Occupancy', 'CO');
    
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @ApplicationTypeId AND PageSubType = 'Revalidation')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@ApplicationTypeId, 'Revalidation', 'REV');
END

-- Bill Notice subtypes
DECLARE @BillNoticeTypeId INT = (SELECT id FROM PageType WHERE PageType = 'Bill Notice');
IF @BillNoticeTypeId IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @BillNoticeTypeId AND PageSubType = 'Demand for Ground Rent')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@BillNoticeTypeId, 'Demand for Ground Rent', 'DGR');
    
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @BillNoticeTypeId AND PageSubType = 'Demand Notice')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@BillNoticeTypeId, 'Demand Notice', 'DN');
END

-- Correspondence subtypes
DECLARE @CorrespondenceTypeId INT = (SELECT id FROM PageType WHERE PageType = 'Correspondence');
IF @CorrespondenceTypeId IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @CorrespondenceTypeId AND PageSubType = 'Acknowledgment Letter')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@CorrespondenceTypeId, 'Acknowledgment Letter', 'AL');
    
    IF NOT EXISTS (SELECT 1 FROM PageSubType WHERE PageTypeId = @CorrespondenceTypeId AND PageSubType = 'Application Submission')
        INSERT INTO PageSubType (PageTypeId, PageSubType, Code) VALUES (@CorrespondenceTypeId, 'Application Submission', 'ASR');
END