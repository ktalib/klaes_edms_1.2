/* Quick check script for application_buyers table */

SELECT TOP 10 *
FROM dbo.application_buyers
ORDER BY id DESC;

SELECT COUNT(*) AS buyers_count
FROM dbo.application_buyers;
