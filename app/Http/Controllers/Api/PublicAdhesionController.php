<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdherentDossier;
use App\Models\AdhesionLink;
use App\Support\DataUrlImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PublicAdhesionController extends Controller
{
    public function showLink(string $code): JsonResponse
    {
        $link = AdhesionLink::where('code', $code)->firstOrFail();
        if ($link->actif) {
            $link->visits()->create();
        }

        return response()->json([
            'code' => $link->code,
            'nom' => $link->nom,
            'actif' => $link->actif,
        ]);
    }

    public function storeDossier(Request $request, string $code): JsonResponse
    {
        $link = AdhesionLink::where('code', $code)->where('actif', true)->firstOrFail();

        $data = $request->validate([
            'civilite' => ['required', Rule::in(['M', 'Mme', 'Mlle'])],
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['required', 'string', 'max:255'],
            'matricule' => ['required', 'regex:/^\d{6}[A-Z]$/', Rule::unique('adherent_dossiers', 'matricule')],
            'emploi' => ['required', 'string', 'max:255'],
            'fonctionSyndicale' => ['nullable', 'string', 'max:255'],
            'employeur' => ['required', 'string', 'max:255'],
            'etablissement' => ['required', 'string', 'max:255'],
            'dejaSyndique' => ['required', 'boolean'],
            'syndicats' => ['nullable', 'required_if:dejaSyndique,true', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'lieu' => ['required', 'string', 'max:255'],
            'precompte' => ['required', 'accepted'],
            'consentement' => ['required', 'accepted'],
            'photo' => ['required', 'string', 'max:7000000'],
            'cniRecto' => ['required', 'string', 'max:7000000'],
            'cniVerso' => ['required', 'string', 'max:7000000'],
            'signature.data' => ['required', 'string', 'max:7000000'],
        ]);

        $data['matricule'] = strtoupper(preg_replace('/\s+/', '', $data['matricule']));
        if (strlen(preg_replace('/\D/', '', $data['telephone'])) !== 10) {
            throw ValidationException::withMessages([
                'telephone' => ['Le numéro doit contenir exactement 10 chiffres.'],
            ]);
        }
        if (! empty($data['whatsapp']) && strlen(preg_replace('/\D/', '', $data['whatsapp'])) !== 10) {
            throw ValidationException::withMessages([
                'whatsapp' => ['Le numéro doit contenir exactement 10 chiffres.'],
            ]);
        }

        $images = [
            'photo_path' => DataUrlImage::decode($data['photo'], 'photo'),
            'cni_recto_path' => DataUrlImage::decode($data['cniRecto'], 'cniRecto'),
            'cni_verso_path' => DataUrlImage::decode($data['cniVerso'], 'cniVerso'),
            'signature_path' => DataUrlImage::decode($data['signature']['data'], 'signature.data'),
        ];
        $storedPaths = [];

        try {
            foreach ($images as $attribute => $image) {
                $path = 'dossiers/'.now()->format('Y').'/'.Str::uuid().'.'.$image['extension'];
                $storedPaths[$attribute] = $path;
                if (! Storage::disk('local')->put($path, $image['contents'])) {
                    throw new RuntimeException("Impossible d'enregistrer le document {$attribute}.");
                }
            }

            $dossier = DB::transaction(function () use ($data, $link, $storedPaths) {
                $dossier = AdherentDossier::create([
                    'numero' => 'DOS-PENDING-'.Str::uuid(),
                    'civilite' => $data['civilite'],
                    'nom' => mb_strtoupper(trim($data['nom'])),
                    'prenoms' => trim($data['prenoms']),
                    'matricule' => $data['matricule'],
                    'emploi' => trim($data['emploi']),
                    'fonction_syndicale' => filled($data['fonctionSyndicale'] ?? null) ? trim($data['fonctionSyndicale']) : null,
                    'employeur' => trim($data['employeur']),
                    'etablissement' => trim($data['etablissement']),
                    'deja_syndique' => $data['dejaSyndique'],
                    'syndicat_precedent' => $data['dejaSyndique'] ? trim($data['syndicats']) : null,
                    'telephone' => trim($data['telephone']),
                    'whatsapp' => filled($data['whatsapp'] ?? null) ? trim($data['whatsapp']) : null,
                    'lieu' => trim($data['lieu']),
                    'precompte' => true,
                    'consentement' => true,
                    ...$storedPaths,
                    'adhesion_link_id' => $link->id,
                ]);

                $dossier->update([
                    'numero' => sprintf('DOS-%s-%05d', now()->format('Y'), $dossier->id),
                ]);

                return $dossier->fresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_values($storedPaths));
            throw $exception;
        }

        return response()->json([
            'id' => $dossier->id,
            'numero' => $dossier->numero,
            'created_at' => $dossier->created_at,
        ], 201);
    }
}
