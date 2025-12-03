<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists before modifying
        if (Schema::hasTable('filtered_words')) {
            $driver = DB::getDriverName();
            
            if ($driver === 'pgsql') {
                // PostgreSQL: Laravel creates CHECK constraints for enums
                // Drop the old constraint and create a new one with 'all'
                try {
                    // Find and drop the existing constraint
                    $constraint = DB::selectOne("
                        SELECT constraint_name 
                        FROM information_schema.table_constraints 
                        WHERE table_name = 'filtered_words' 
                        AND constraint_type = 'CHECK'
                        AND constraint_name LIKE '%applies_to%'
                    ");
                    
                    if ($constraint) {
                        DB::statement("ALTER TABLE filtered_words DROP CONSTRAINT IF EXISTS {$constraint->constraint_name}");
                    }
                    
                    // Add new constraint with 'all' option
                    DB::statement("ALTER TABLE filtered_words ADD CONSTRAINT filtered_words_applies_to_check CHECK (applies_to IN ('chats', 'names', 'bios', 'walls', 'statuses', 'all'))");
                } catch (\Exception $e) {
                    // If constraint doesn't exist or error occurs, try alternative approach
                    // Just ensure the column allows 'all' value
                    DB::statement("ALTER TABLE filtered_words DROP CONSTRAINT IF EXISTS filtered_words_applies_to_check");
                    DB::statement("ALTER TABLE filtered_words ADD CONSTRAINT filtered_words_applies_to_check CHECK (applies_to IN ('chats', 'names', 'bios', 'walls', 'statuses', 'all'))");
                }
            } else {
                // MySQL/MariaDB
                $columnInfo = DB::select("SHOW COLUMNS FROM filtered_words WHERE Field = 'applies_to'");
                if (!empty($columnInfo)) {
                    $columnType = $columnInfo[0]->Type;
                    if (strpos($columnType, "'all'") === false) {
                        DB::statement("ALTER TABLE filtered_words MODIFY COLUMN applies_to ENUM('chats', 'names', 'bios', 'walls', 'statuses', 'all') DEFAULT 'chats'");
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('filtered_words')) {
            $driver = DB::getDriverName();
            
            if ($driver === 'pgsql') {
                // PostgreSQL: Drop and recreate constraint without 'all'
                try {
                    DB::statement("ALTER TABLE filtered_words DROP CONSTRAINT IF EXISTS filtered_words_applies_to_check");
                    DB::statement("ALTER TABLE filtered_words ADD CONSTRAINT filtered_words_applies_to_check CHECK (applies_to IN ('chats', 'names', 'bios', 'walls', 'statuses'))");
                } catch (\Exception $e) {
                    // Ignore errors on rollback
                }
            } else {
                // MySQL/MariaDB
                DB::statement("ALTER TABLE filtered_words MODIFY COLUMN applies_to ENUM('chats', 'names', 'bios', 'walls', 'statuses') DEFAULT 'chats'");
            }
        }
    }
};

