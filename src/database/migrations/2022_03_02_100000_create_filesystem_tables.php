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
        Schema::create(
            'fs_items',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->bigInteger('user_id')->index();
                $table->integer('type')->unsigned()->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')->references('id')->on('users')
                    ->onUpdate('cascade')->onDelete('cascade');
            }
        );

        Schema::create(
            'fs_properties',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('item_id', 36);
                $table->string('key')->index();
                $table->text('value');

                $table->timestamps();

                $table->unique(['item_id', 'key']);

                $table->foreign('item_id')->references('id')->on('fs_items')
                    ->onDelete('cascade')->onUpdate('cascade');
            }
        );

        Schema::create(
            'fs_chunks',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('item_id', 36);
                $table->string('chunk_id', 36);
                $table->integer('sequence')->default(0);
                $table->integer('size')->unsigned()->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['item_id', 'chunk_id']);
                // $table->unique(['item_id', 'sequence', 'deleted_at']);

                $table->foreign('item_id')->references('id')->on('fs_items')
                    ->onUpdate('cascade')->onDelete('cascade');
            }
        );

        if (!\App\Sku::where('title', 'files')->first()) {
            \App\Sku::create([
                'title' => 'files',
                'name' => 'File storage',
                'description' => 'Access to file storage',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Files',
                'active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fs_properties');
        Schema::dropIfExists('fs_chunks');
        Schema::dropIfExists('fs_items');
    }
};
