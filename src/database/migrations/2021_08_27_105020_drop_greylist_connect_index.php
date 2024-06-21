<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class DropGreylistConnectIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'greylist_connect',
            function (Blueprint $table) {
                $table->dropIndex('ssrnn_idx');
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
        Schema::table(
            'greylist_connect',
            function (Blueprint $table) {
                /**
                 * Index for recipient request.
                 */
                $table->index(
                    [
                        'sender_local',
                        'sender_domain',
                        'recipient_hash',
                        'net_id',
                        'net_type'
                    ],
                    'ssrnn_idx'
                );
            }
        );
    }
}
