<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdherentDossier;
use App\Models\AdhesionLink;
use App\Support\DataUrlDocument;
use App\Support\DataUrlImage;
use App\Support\SyndicalRegion;
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
            'regionSyndicale' => ['required', Rule::in(SyndicalRegion::VALUES)],
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
            'synergieAssistance' => ['required', 'boolean'],
            'assistanceBeneficiaries' => ['exclude_unless:synergieAssistance,true', 'required', 'array', 'min:1', 'max:8'],
            'assistanceBeneficiaries.*.nom' => ['required', 'string', 'max:255'],
            'assistanceBeneficiaries.*.prenoms' => ['required', 'string', 'max:255'],
            'assistanceBeneficiaries.*.lienParente' => ['required', Rule::in(['conjoint', 'descendant', 'ascendant', 'substitut'])],
            'assistanceBeneficiaries.*.parentsDecedes' => ['nullable', 'boolean'],
            'assistanceBeneficiaries.*.dateNaissance' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'assistanceBeneficiaries.*.telephone' => ['required', 'string', 'max:30'],
            'assistanceBeneficiaries.*.lieuResidence' => ['required', 'string', 'max:255'],
            'assistanceBeneficiaries.*.justificatif' => ['required', 'string', 'max:3000000'],
            'assistanceBeneficiaries.*.justificatifNom' => ['required', 'string', 'max:255'],
        ]);

        foreach ($data['assistanceBeneficiaries'] ?? [] as $index => $beneficiary) {
            if (strlen(preg_replace('/\D/', '', $beneficiary['telephone'])) !== 10) {
                throw ValidationException::withMessages([
                    "assistanceBeneficiaries.{$index}.telephone" => ['Le numéro de téléphone doit contenir exactement 10 chiffres.'],
                ]);
            }
            if ($beneficiary['lienParente'] === 'substitut' && ! ($beneficiary['parentsDecedes'] ?? false)) {
                throw ValidationException::withMessages([
                    "assistanceBeneficiaries.{$index}.parentsDecedes" => ['Confirmez que vos deux parents sont décédés avant le début des prestations.'],
                ]);
            }
        }

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
        $assistanceUploads = [];
        foreach ($data['assistanceBeneficiaries'] ?? [] as $index => $beneficiary) {
            $assistanceUploads[$index] = [
                'beneficiary' => $beneficiary,
                'document' => DataUrlDocument::decode(
                    $beneficiary['justificatif'],
                    "assistanceBeneficiaries.{$index}.justificatif",
                ),
            ];
        }
        $storedPaths = [];
        $assistancePaths = [];

        try {
            foreach ($images as $attribute => $image) {
                $path = 'dossiers/'.now()->format('Y').'/'.now()->format('m').'/'.Str::uuid().'.'.$image['extension'];
                $storedPaths[$attribute] = $path;
                if (! Storage::disk('local')->put($path, $image['contents'])) {
                    throw new RuntimeException("Impossible d'enregistrer le document {$attribute}.");
                }
            }

            foreach ($assistanceUploads as $index => $upload) {
                $path = 'dossiers/'.now()->format('Y').'/'.now()->format('m').'/'.Str::uuid().'.'.$upload['document']['extension'];
                $assistancePaths[$index] = $path;
                if (! Storage::disk('local')->put($path, $upload['document']['contents'])) {
                    throw new RuntimeException("Impossible d'enregistrer le justificatif du bénéficiaire {$index}.");
                }
            }

            $dossier = DB::transaction(function () use ($data, $link, $storedPaths, $assistanceUploads, $assistancePaths) {
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
                    'region_syndicale' => $data['regionSyndicale'],
                    'deja_syndique' => $data['dejaSyndique'],
                    'syndicat_precedent' => $data['dejaSyndique'] ? trim($data['syndicats']) : null,
                    'telephone' => trim($data['telephone']),
                    'whatsapp' => filled($data['whatsapp'] ?? null) ? trim($data['whatsapp']) : null,
                    'lieu' => trim($data['lieu']),
                    'precompte' => true,
                    'consentement' => true,
                    'synergie_assistance' => $data['synergieAssistance'],
                    ...$storedPaths,
                    'adhesion_link_id' => $link->id,
                ]);

                foreach ($assistanceUploads as $index => $upload) {
                    $beneficiary = $upload['beneficiary'];
                    $dossier->assistanceBeneficiaries()->create([
                        'nom' => trim($beneficiary['nom']),
                        'prenoms' => trim($beneficiary['prenoms']),
                        'lien_parental' => $beneficiary['lienParente'],
                        'parents_decedes' => $beneficiary['lienParente'] === 'substitut',
                        'date_naissance' => $beneficiary['dateNaissance'] ?? null,
                        'telephone' => trim($beneficiary['telephone']),
                        'lieu_residence' => trim($beneficiary['lieuResidence']),
                        'justificatif_path' => $assistancePaths[$index],
                        'justificatif_nom' => basename(str_replace('\\', '/', trim($beneficiary['justificatifNom']))),
                    ]);
                }

                $dossier->update([
                    'numero' => sprintf('DOS-%s-%05d', now()->format('Y'), $dossier->id),
                ]);

                return $dossier->fresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete([...array_values($storedPaths), ...array_values($assistancePaths)]);
            throw $exception;
        }

        return response()->json([
            'id' => $dossier->id,
            'numero' => $dossier->numero,
            'created_at' => $dossier->created_at,
            'synergie_assistance' => $dossier->synergie_assistance,
            'assistance_beneficiaries' => $dossier->assistanceBeneficiaries->map(fn ($beneficiary) => [
                'id' => $beneficiary->id,
                'nom' => $beneficiary->nom,
                'prenoms' => $beneficiary->prenoms,
                'lien_parental' => $beneficiary->lien_parental,
                'parents_decedes' => $beneficiary->parents_decedes,
                'date_naissance' => $beneficiary->date_naissance,
                'telephone' => $beneficiary->telephone,
                'lieu_residence' => $beneficiary->lieu_residence,
                'justificatif_nom' => $beneficiary->justificatif_nom,
            ]),
        ], 201);
    }
}
