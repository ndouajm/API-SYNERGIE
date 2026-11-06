<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_beneficiaries', function (Blueprint $table) {
            $table->date('date_naissance')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('lieu_residence')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assistance_beneficiaries', function (Blueprint $table) {
            $table->dropColumn(['date_naissance', 'telephone', 'lieu_residence']);
        });
    }
};
