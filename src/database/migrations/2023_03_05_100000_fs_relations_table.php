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
        Schema::create(
            'fs_relations',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('item_id', 36);
                $table->string('related_id', 36);

                $table->foreign('item_id')->references('id')->on('fs_items')
                    ->onDelete('cascade');
                $table->foreign('related_id')->references('id')->on('fs_items')
                    ->onDelete('cascade');
                $table->unique(['item_id', 'related_id']);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fs_relations');
    }
};
