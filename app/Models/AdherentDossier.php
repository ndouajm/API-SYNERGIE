<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdherentDossier extends Model
{
    protected $fillable = [
        'numero',
        'civilite',
        'nom',
        'prenoms',
        'matricule',
        'emploi',
        'fonction_syndicale',
        'employeur',
        'etablissement',
        'region_syndicale',
        'deja_syndique',
        'syndicat_precedent',
        'telephone',
        'whatsapp',
        'lieu',
        'precompte',
        'consentement',
        'synergie_assistance',
        'photo_path',
        'cni_recto_path',
        'cni_verso_path',
        'signature_path',
        'adhesion_link_id',
    ];

    protected function casts(): array
    {
        return [
            'deja_syndique' => 'boolean',
            'precompte' => 'boolean',
            'consentement' => 'boolean',
            'synergie_assistance' => 'boolean',
        ];
    }

    public function adhesionLink(): BelongsTo
    {
        return $this->belongsTo(AdhesionLink::class);
    }

    public function assistanceBeneficiaries(): HasMany
    {
        return $this->hasMany(AssistanceBeneficiary::class);
    }
}
