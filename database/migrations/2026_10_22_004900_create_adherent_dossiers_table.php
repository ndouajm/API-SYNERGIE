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
        Schema::create('adherent_dossiers', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->string('civilite', 10);
            $table->string('nom');
            $table->string('prenoms');
            $table->string('matricule', 7)->unique();
            $table->string('emploi');
            $table->string('fonction_syndicale')->nullable();
            $table->string('employeur');
            $table->string('etablissement');
            $table->boolean('deja_syndique')->default(false);
            $table->string('syndicat_precedent')->nullable();
            $table->string('telephone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('lieu');
            $table->boolean('precompte')->default(false);
            $table->boolean('consentement')->default(false);
            $table->string('photo_path');
            $table->string('cni_recto_path');
            $table->string('cni_verso_path');
            $table->string('signature_path');
            $table->foreignId('adhesion_link_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adherent_dossiers');
    }
};
