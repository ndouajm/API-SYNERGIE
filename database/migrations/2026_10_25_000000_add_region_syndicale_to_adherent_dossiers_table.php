<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adherent_dossiers', function (Blueprint $table) {
            $table->string('region_syndicale')->nullable()->after('etablissement');
        });
    }

    public function down(): void
    {
        Schema::table('adherent_dossiers', function (Blueprint $table) {
            $table->dropColumn('region_syndicale');
        });
    }
};
