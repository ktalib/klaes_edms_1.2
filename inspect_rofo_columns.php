<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel if needed (assuming this runs via artisan tinker or similar context, but for run_command properly we might need to be inside the app context)
// Actually, run_command with php artisan tinker is interactive and hard.
// Better to simple create a route or command, or just use a migration to inspect/log.
// OR simpler: Try to run a raw query via a temporary command.

// But wait, the USER said "save in ground_rent".
// I will create a migration that RENAME recertification_fee to ground_rent IF ground_rent does not exist,
// OR if ground_rent exists, I will just update the controller.

// Let's assume the user is right and `ground_rent` acts as the target.
// I'll assume `recertification_fee` was the OLD name or a mistake.
// I will update the controller to map `recertification_fee` input -> `ground_rent` column.
// BUT, to be safe, I should ensure the column `ground_rent` exists.

// I'll create a migration that adds `ground_rent` if it doesn't exist, or modifies it to string if it does.
// AND I will check if `recertification_fee` exists and maybe drop it or migrate data?
// For now, let's just ensure `ground_rent` is available as a string column.

echo "Table: rofo\n";
$columns = Schema::getColumnListing('rofo');
print_r($columns);
