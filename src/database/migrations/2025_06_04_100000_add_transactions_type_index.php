<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(
            'transactions',
            static function (Blueprint $table) {
                $table->index(['type'], 'type_idx');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'transactions',
            static function (Blueprint $table) {
                $table->dropIndex('type_idx');
            }
        );
    }
};
