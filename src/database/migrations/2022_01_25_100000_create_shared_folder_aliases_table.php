<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSharedFolderAliasesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'shared_folder_aliases',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('shared_folder_id');
                $table->string('alias');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique(['alias', 'shared_folder_id']);

                $table->foreign('shared_folder_id')->references('id')->on('shared_folders')
                    ->onDelete('cascade')->onUpdate('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('shared_folder_aliases');
    }
}
