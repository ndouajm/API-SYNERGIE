<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('assistance_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adherent_dossier_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('prenoms');
            $table->string('lien_parental', 30);
            $table->boolean('parents_decedes')->default(false);
            $table->string('justificatif_path');
            $table->string('justificatif_nom');
            $table->date('date_naissance')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('lieu_residence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_beneficiaries');

        Schema::table('adherent_dossiers', function (Blueprint $table) {
            $table->dropColumn('synergie_assistance');
        });
    }
};
