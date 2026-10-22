<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdherentDossier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DossierController extends Controller
{
    public function index(Request $request): JsonResponse
    {

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = AdherentDossier::query()->latest();
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenoms', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%")
                    ->orWhere('numero', 'like', "%{$search}%")
                    ->orWhere('emploi', 'like', "%{$search}%")
                    ->orWhere('fonction_syndicale', 'like', "%{$search}%")
                    ->orWhere('etablissement', 'like', "%{$search}%");
            });
        }

        $dossiers = $query->paginate($filters['per_page'] ?? 25);

        return response()->json([
            'data' => $dossiers->getCollection()->map(fn (AdherentDossier $dossier) => $this->serialize($dossier, $request->user()->hasPermission('documents.view'))),
            'meta' => [
                'current_page' => $dossiers->currentPage(),
                'last_page' => $dossiers->lastPage(),
                'per_page' => $dossiers->perPage(),
                'total' => $dossiers->total(),
            ],
        ]);
    }

    public function show(Request $request, AdherentDossier $dossier): JsonResponse
    {
        return response()->json($this->serialize($dossier, $request->user()->hasPermission('documents.view')));
    }

    public function update(Request $request, AdherentDossier $dossier): JsonResponse
    {
        $data = $request->validate([
            'civilite' => ['sometimes', 'required', Rule::in(['M', 'Mme', 'Mlle'])],
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'prenoms' => ['sometimes', 'required', 'string', 'max:255'],
            'matricule' => ['sometimes', 'required', 'regex:/^\d{6}[A-Z]$/', Rule::unique('adherent_dossiers', 'matricule')->ignore($dossier->id)],
            'emploi' => ['sometimes', 'required', 'string', 'max:255'],
            'fonction_syndicale' => ['nullable', 'string', 'max:255'],
            'employeur' => ['sometimes', 'required', 'string', 'max:255'],
            'etablissement' => ['sometimes', 'required', 'string', 'max:255'],
            'deja_syndique' => ['sometimes', 'required', 'boolean'],
            'syndicat_precedent' => ['nullable', 'required_if:deja_syndique,true', 'string', 'max:255'],
            'telephone' => ['sometimes', 'required', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'lieu' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        if (array_key_exists('matricule', $data)) {
            $data['matricule'] = strtoupper(preg_replace('/\s+/', '', $data['matricule']));
        }
        if (array_key_exists('deja_syndique', $data) && ! $data['deja_syndique']) {
            $data['syndicat_precedent'] = null;
        }
        if (array_key_exists('telephone', $data) && strlen(preg_replace('/\D/', '', $data['telephone'])) !== 10) {
            return response()->json(['message' => 'Le téléphone doit contenir exactement 10 chiffres.', 'errors' => ['telephone' => ['Le téléphone doit contenir exactement 10 chiffres.']]], 422);
        }
        if (! empty($data['whatsapp']) && strlen(preg_replace('/\D/', '', $data['whatsapp'])) !== 10) {
            return response()->json(['message' => 'Le WhatsApp doit contenir exactement 10 chiffres.', 'errors' => ['whatsapp' => ['Le WhatsApp doit contenir exactement 10 chiffres.']]], 422);
        }

        $dossier->update($data);

        return response()->json($this->serialize($dossier->fresh(), $request->user()->hasPermission('documents.view')));
    }

    public function export(): StreamedResponse
    {
        $fields = [
            'N° dossier', 'Civilité', 'Nom', 'Prénoms', 'Matricule', 'Emploi', 'Fonction syndicale',
            'Employeur', 'Établissement', 'Syndicat précédent', 'Téléphone', 'WhatsApp', 'Fait à', 'Date',
        ];

        return Response::streamDownload(function () use ($fields) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $fields, ';');
            foreach (AdherentDossier::query()->latest()->cursor() as $dossier) {
                $cells = [
                    $dossier->numero,
                    $dossier->civilite,
                    $dossier->nom,
                    $dossier->prenoms,
                    $dossier->matricule,
                    $dossier->emploi,
                    $dossier->fonction_syndicale,
                    $dossier->employeur,
                    $dossier->etablissement,
                    $dossier->syndicat_precedent,
                    $dossier->telephone,
                    $dossier->whatsapp,
                    $dossier->lieu,
                    $dossier->created_at?->format('Y-m-d H:i:s'),
                ];
                $cells = array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value, $cells);
                fputcsv($output, [
                    ...$cells,
                ], ';', '"', '');
            }
            fclose($output);
        }, 'adhesions-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(AdherentDossier $dossier): JsonResponse
    {
        $documentPaths = array_filter([
            $dossier->photo_path,
            $dossier->cni_recto_path,
            $dossier->cni_verso_path,
            $dossier->signature_path,
        ]);

        $dossier->delete();

        if ($documentPaths !== [] && ! Storage::disk('local')->delete($documentPaths)) {
            throw new RuntimeException("Le dossier {$dossier->numero} a été supprimé, mais ses pièces jointes n'ont pas toutes pu être effacées.");
        }

        return response()->json(['message' => 'Dossier et pièces jointes supprimés.']);
    }

    public function document(AdherentDossier $dossier, string $document): StreamedResponse
    {
        $pathAttribute = match ($document) {
            'photo' => 'photo_path',
            'cni-recto' => 'cni_recto_path',
            'cni-verso' => 'cni_verso_path',
            'signature' => 'signature_path',
            default => abort(404),
        };
        $path = $dossier->{$pathAttribute};
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function serialize(AdherentDossier $dossier, bool $includeDocuments): array
    {
        return [
            'id' => $dossier->id,
            'numero' => $dossier->numero,
            'civilite' => $dossier->civilite,
            'nom' => $dossier->nom,
            'prenoms' => $dossier->prenoms,
            'matricule' => $dossier->matricule,
            'emploi' => $dossier->emploi,
            'fonction_syndicale' => $dossier->fonction_syndicale,
            'employeur' => $dossier->employeur,
            'etablissement' => $dossier->etablissement,
            'deja_syndique' => $dossier->deja_syndique,
            'syndicat_precedent' => $dossier->syndicat_precedent,
            'telephone' => $dossier->telephone,
            'whatsapp' => $dossier->whatsapp,
            'lieu' => $dossier->lieu,
            'precompte' => $dossier->precompte,
            'consentement' => $dossier->consentement,
            'has_signature' => $dossier->signature_path !== null,
            'documents' => $includeDocuments ? [
                'photo' => $this->documentUrl($dossier, 'photo'),
                'cni_recto' => $this->documentUrl($dossier, 'cni-recto'),
                'cni_verso' => $this->documentUrl($dossier, 'cni-verso'),
                'signature' => $this->documentUrl($dossier, 'signature'),
            ] : null,
            'adhesion_link_id' => $dossier->adhesion_link_id,
            'created_at' => $dossier->created_at,
        ];
    }

    private function documentUrl(AdherentDossier $dossier, string $document): string
    {
        return route(
            'api.admin.dossiers.documents.show',
            ['dossier' => $dossier, 'document' => $document],
        );
    }
}
