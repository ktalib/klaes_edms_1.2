<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Create the sequence table
        if (!Schema::connection('sqlsrv')->hasTable('temp_fileno_sequence')) {
            Schema::connection('sqlsrv')->create('temp_fileno_sequence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            // 2. Calculate the maximum existing TEMP number
            // We need to look at 'pra', 'pic', and 'instrument_capture' tables
            // Format is TEMP-00001, so we strip 'TEMP-' and cast to INT

            $maxTemp = 0;

            try {
                $sql = "
                    SELECT MAX(val) as max_val FROM (
                        SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT) as val 
                        FROM pra 
                        WHERE temp_fileno LIKE 'TEMP-%' AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1
                        
                        UNION ALL
                        
                        SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT) as val 
                        FROM pic 
                        WHERE temp_fileno LIKE 'TEMP-%' AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1

                        UNION ALL
                        
                        SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT) as val 
                        FROM instrument_capture 
                        WHERE temp_fileno LIKE 'TEMP-%' AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1
                    ) as combined_tables
                ";

                // Only run max query if tables exist
                if (
                    Schema::connection('sqlsrv')->hasTable('pra') &&
                    Schema::connection('sqlsrv')->hasTable('pic') &&
                    Schema::connection('sqlsrv')->hasTable('instrument_capture')
                ) {

                    $result = DB::connection('sqlsrv')->selectOne($sql);

                    if ($result && $result->max_val) {
                        $maxTemp = (int) $result->max_val;
                    }
                }
            } catch (\Exception $e) {
                // Should only default to 0 if tables don't exist yet (fresh install)
                // Log::warning("Could not determine max temp fileno (tables might be missing): " . $e->getMessage());
            }

            // 3. Reseed the identity column to start AFTER the max value
            // DBCC CHECKIDENT ('table', RESEED, new_reseed_value) sets the *current* value.
            // The *next* inserted value will be new_reseed_value + 1.
            // So if maxTemp is 5000, we reseed to 5000, and next insert gets 5001.
            if ($maxTemp > 0) {
                DB::connection('sqlsrv')->statement("DBCC CHECKIDENT ('temp_fileno_sequence', RESEED, {$maxTemp})");
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('temp_fileno_sequence');
    }
};
