-- Mark migration as completed since table already exists
INSERT INTO [migrations] ([migration], [batch]) 
VALUES ('2025_10_02_000001_create_file_number_reservations_table', 2);