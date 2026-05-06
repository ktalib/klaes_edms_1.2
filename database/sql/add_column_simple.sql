-- Simple script to add application_date column
USE klas;

-- Add the column
ALTER TABLE mother_applications 
ADD application_date DATE NULL;