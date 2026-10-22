<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkVisit extends Model
{
    protected $fillable = ['adhesion_link_id'];

    public function adhesionLink(): BelongsTo
    {
        return $this->belongsTo(AdhesionLink::class);
    }
}
