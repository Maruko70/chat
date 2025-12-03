<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run if table exists (in case it was already created)
        if (!Schema::hasTable('role_groups')) {
            return;
        }

        $columnsToDrop = [];
        
        // Check which old columns exist and need to be dropped
        if (Schema::hasColumn('role_groups', 'color')) {
            $columnsToDrop[] = 'color';
        }
        if (Schema::hasColumn('role_groups', 'icon')) {
            $columnsToDrop[] = 'icon';
        }
        if (Schema::hasColumn('role_groups', 'description')) {
            $columnsToDrop[] = 'description';
        }

        // Drop old columns if any exist
        if (!empty($columnsToDrop)) {
            Schema::table('role_groups', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
        
        // Add banner field if it doesn't exist
        if (!Schema::hasColumn('role_groups', 'banner')) {
            Schema::table('role_groups', function (Blueprint $table) {
                $table->string('banner')->nullable()->after('slug');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('role_groups')) {
            return;
        }

        // Remove banner if it exists
        if (Schema::hasColumn('role_groups', 'banner')) {
            Schema::table('role_groups', function (Blueprint $table) {
                $table->dropColumn('banner');
            });
        }

        // Restore old fields if they don't exist
        $columnsToAdd = [];
        if (!Schema::hasColumn('role_groups', 'color')) {
            $columnsToAdd[] = ['name' => 'color', 'type' => 'string', 'after' => 'slug'];
        }
        if (!Schema::hasColumn('role_groups', 'icon')) {
            $columnsToAdd[] = ['name' => 'icon', 'type' => 'string', 'after' => 'color'];
        }
        if (!Schema::hasColumn('role_groups', 'description')) {
            $columnsToAdd[] = ['name' => 'description', 'type' => 'text', 'after' => 'icon'];
        }

        if (!empty($columnsToAdd)) {
            Schema::table('role_groups', function (Blueprint $table) use ($columnsToAdd) {
                foreach ($columnsToAdd as $column) {
                    if ($column['type'] === 'string') {
                        $table->string($column['name'])->nullable()->after($column['after']);
                    } elseif ($column['type'] === 'text') {
                        $table->text($column['name'])->nullable()->after($column['after']);
                    }
                }
            });
        }
    }
};
