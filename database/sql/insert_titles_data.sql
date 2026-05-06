-- Insert titles lookup data for KLAES system
-- Run this script manually if migration fails due to permissions

-- Create titles table if it doesn't exist
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='titles' AND xtype='U')
BEGIN
    CREATE TABLE titles (
        id int IDENTITY(1,1) PRIMARY KEY,
        title nvarchar(50) UNIQUE NOT NULL,
        display_name nvarchar(100) NOT NULL,
        is_active bit DEFAULT 1,
        sort_order int DEFAULT 0,
        created_at datetime2 DEFAULT GETDATE(),
        updated_at datetime2 DEFAULT GETDATE()
    );
END

-- Clear existing data
DELETE FROM titles;

-- Insert predefined titles
INSERT INTO titles (title, display_name, sort_order, is_active, created_at, updated_at) VALUES
('Mr.', 'Mr.', 1, 1, GETDATE(), GETDATE()),
('Mrs.', 'Mrs.', 2, 1, GETDATE(), GETDATE()),
('Miss', 'Miss', 3, 1, GETDATE(), GETDATE()),
('Dr.', 'Dr.', 4, 1, GETDATE(), GETDATE()),
('Prof', 'Prof.', 5, 1, GETDATE(), GETDATE()),
('Barr.', 'Barr.', 6, 1, GETDATE(), GETDATE()),
('Arc.', 'Arc.', 7, 1, GETDATE(), GETDATE()),
('Chief', 'Chief', 8, 1, GETDATE(), GETDATE()),
('High Chief', 'High Chief', 9, 1, GETDATE(), GETDATE()),
('Alhaji', 'Alhaji', 10, 1, GETDATE(), GETDATE()),
('Hajia', 'Hajia', 11, 1, GETDATE(), GETDATE()),
('Mallam', 'Mallam', 12, 1, GETDATE(), GETDATE()),
('Senator', 'Senator', 13, 1, GETDATE(), GETDATE()),
('Honorable', 'Honorable', 14, 1, GETDATE(), GETDATE()),
('Capt', 'Capt.', 15, 1, GETDATE(), GETDATE()),
('Coln', 'Col.', 16, 1, GETDATE(), GETDATE()),
('Master', 'Master', 17, 1, GETDATE(), GETDATE()),
('HRH', 'HRH', 18, 1, GETDATE(), GETDATE()),
('Messr', 'Messr.', 19, 1, GETDATE(), GETDATE()),
('Other', 'Other', 20, 1, GETDATE(), GETDATE());

-- Verify the data was inserted
SELECT * FROM titles ORDER BY sort_order;

PRINT 'Titles lookup table created and populated successfully.';