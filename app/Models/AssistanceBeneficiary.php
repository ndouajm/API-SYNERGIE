<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceBeneficiary extends Model
{
    protected $fillable = [
        'nom',
        'prenoms',
        'adherent_dossier_id',
        'lien_parental',
        'parents_decedes',
        'date_naissance',
        'telephone',
        'lieu_residence',
        'justificatif_path',
        'justificatif_nom',
    ];

    protected function casts(): array
    {
        return [
            'parents_decedes' => 'boolean',
        ];
    }

    public function adherentDossier(): BelongsTo
    {
        return $this->belongsTo(AdherentDossier::class);
    }
}
