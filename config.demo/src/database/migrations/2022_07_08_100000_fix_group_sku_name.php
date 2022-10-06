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
        \App\Sku::where('title', 'group')->get()->each(function ($sku) {
            $sku->name = 'Distribution list';
            $sku->description = 'Mail distribution list';
            $sku->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Sku::where('title', 'group')->get()->each(function ($sku) {
            $sku->name = 'Group';
            $sku->description = 'Distribution list';
            $sku->save();
        });
    }
};
