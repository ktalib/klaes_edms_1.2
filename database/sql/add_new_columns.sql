-- Add new columns to property_records table
USE [your_database_name]
GO

ALTER TABLE property_records ADD tp_no NVARCHAR(255) NULL;
ALTER TABLE property_records ADD lpkn_no NVARCHAR(255) NULL;
ALTER TABLE property_records ADD approved_plan_no NVARCHAR(255) NULL;
ALTER TABLE property_records ADD plot_size NVARCHAR(255) NULL;
ALTER TABLE property_records ADD date_recommended DATE NULL;
ALTER TABLE property_records ADD date_approved DATE NULL;
ALTER TABLE property_records ADD lease_begins DATE NULL;
ALTER TABLE property_records ADD lease_expires DATE NULL;
ALTER TABLE property_records ADD metric_sheet NVARCHAR(255) NULL;
