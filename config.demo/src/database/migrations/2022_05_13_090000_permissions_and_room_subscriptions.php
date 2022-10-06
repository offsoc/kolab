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
        // Create the new SKUs
        \App\Sku::create([
                'title' => 'group-room',
                'name' => 'Group conference room',
                'description' => 'Shareable audio & video conference room',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\GroupRoom',
                'active' => true,
        ]);

        \App\Sku::create([
                'title' => 'room',
                'name' => 'Standard conference room',
                'description' => 'Audio & video conference room',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Room',
                'active' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Sku::where('title', 'room')->delete();
        \App\Sku::where('title', 'group-room')->delete();

        \App\Sku::create([
                'title' => 'meet',
                'name' => 'Voice & Video Conferencing (public beta)',
                'description' => 'Video conferencing tool',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Meet',
                'active' => true,
        ]);
    }
};
