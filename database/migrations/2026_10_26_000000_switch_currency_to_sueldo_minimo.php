<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasa la economía del juego de "monedas" (₲ 100 por tirada) a sueldos mínimos
     * (₲ 3.044.000 por tirada, Decreto 6225/2026). Los saldos existentes se
     * reescalan para conservar la cantidad de tiradas disponibles.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('coins')->default(9132000)->change();
        });

        DB::table('players')->update([
            'coins' => DB::raw('coins * 30440'),
        ]);
    }

    public function down(): void
    {
        DB::table('players')->update([
            'coins' => DB::raw('ROUND(coins / 30440)'),
        ]);

        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('coins')->default(1500)->change();
        });
    }
};
