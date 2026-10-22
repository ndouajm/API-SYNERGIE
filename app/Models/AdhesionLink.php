<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdhesionLink extends Model
{
    protected $fillable = ['code', 'nom', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function dossiers(): HasMany
    {
        return $this->hasMany(AdherentDossier::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(LinkVisit::class);
    }
}
