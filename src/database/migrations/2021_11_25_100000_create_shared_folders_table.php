<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class CreateSharedFoldersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'shared_folders',
            function (Blueprint $table) {
                $table->unsignedBigInteger('id');
                $table->string('email')->unique();
                $table->string('name');
                $table->string('type', 8);
                $table->smallInteger('status');
                $table->unsignedBigInteger('tenant_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->primary('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        );

        Schema::create(
            'shared_folder_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('shared_folder_id');
                $table->string('key');
                $table->text('value');
                $table->timestamps();

                $table->foreign('shared_folder_id')->references('id')->on('shared_folders')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->unique(['shared_folder_id', 'key']);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shared_folder_settings');
        Schema::dropIfExists('shared_folders');

        // there's no need to remove the SKU
    }
}
