<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExpiresAtColumnFix extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // This is to remove "ON UPDATE" clause from the column
        // There's no way to do this with Laravel schema migration tools,
        // we have to use raw queries.

        // Note: Whenever you create a table with custom timestamp() column don't forget to use
        // also one of nullable() or useCurrent() to make sure "ON UPDATE" is not added.

        DB::statement("ALTER TABLE `signup_codes` MODIFY `expires_at`"
            . " TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        DB::statement("ALTER TABLE `verification_codes` MODIFY `expires_at`"
            . " TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // empty
    }
}
