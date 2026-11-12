-- Physical Planning: Commercial betterment base fee is N35,000 (was N25,000).
-- Only affects new calculations; previously generated bills are unchanged.
UPDATE pp_betterment_rates
SET base_fee = 35000, updated_at = GETDATE()
WHERE category = 'Commercial';
