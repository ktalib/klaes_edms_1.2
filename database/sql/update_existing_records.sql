-- Update existing records with application_date
USE klas;

-- Update existing records to use created_at date as application_date
UPDATE mother_applications 
SET application_date = CAST(created_at AS DATE)
WHERE application_date IS NULL;