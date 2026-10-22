<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdherentDossier;
use App\Models\AdhesionLink;
use App\Models\LinkVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $since = now()->subDays(30);
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $selectedYear = (int) ($filters['year'] ?? now()->year);
        $driver = DB::connection()->getDriverName();
        $yearExpression = $driver === 'sqlite'
            ? "CAST(strftime('%Y', created_at) AS INTEGER)"
            : 'YEAR(created_at)';
        $monthExpression = $driver === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';
        $monthlyVisits = LinkVisit::query()
            ->selectRaw("{$yearExpression} as year, {$monthExpression} as month, COUNT(*) as total")
            ->groupByRaw("{$yearExpression}, {$monthExpression}")
            ->get()
            ->keyBy(fn ($row) => "{$row->year}-{$row->month}");
        $monthlySubmissions = AdherentDossier::query()
            ->selectRaw("{$yearExpression} as year, {$monthExpression} as month, COUNT(*) as total")
            ->groupByRaw("{$yearExpression}, {$monthExpression}")
            ->get()
            ->keyBy(fn ($row) => "{$row->year}-{$row->month}");
        $yearlyVisits = LinkVisit::query()
            ->selectRaw("{$yearExpression} as year, COUNT(*) as total")
            ->groupByRaw($yearExpression)
            ->get()
            ->keyBy('year');
        $yearlySubmissions = AdherentDossier::query()
            ->selectRaw("{$yearExpression} as year, COUNT(*) as total")
            ->groupByRaw($yearExpression)
            ->get()
            ->keyBy('year');
        $years = collect([$monthlyVisits, $monthlySubmissions])
            ->flatMap(fn ($groups) => $groups->pluck('year'))
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();
        $links = AdhesionLink::query()
            ->withCount(['visits', 'dossiers'])
            ->latest()
            ->get();
        $monthRows = collect(range(1, 12))->map(fn ($month) => [
            'month' => $month,
            'visits' => (int) ($monthlyVisits->get("{$selectedYear}-{$month}")->total ?? 0),
            'submissions' => (int) ($monthlySubmissions->get("{$selectedYear}-{$month}")->total ?? 0),
        ]);

        return response()->json([
            'dossiers_total' => AdherentDossier::count(),
            'dossiers_30_jours' => AdherentDossier::where('created_at', '>=', $since)->count(),
            'liens_actifs' => $links->where('actif', true)->count(),
            'liens_total' => $links->count(),
            'visites_total' => LinkVisit::count(),
            'soumissions_total' => AdherentDossier::count(),
            'signatures_total' => AdherentDossier::whereNotNull('signature_path')->count(),
            'annees' => $years,
            'par_mois' => $monthRows,
            'par_annee' => $years->map(fn ($year) => [
                'annee' => $year,
                'visites' => (int) ($yearlyVisits->get($year)->total ?? 0),
                'soumissions' => (int) ($yearlySubmissions->get($year)->total ?? 0),
            ]),
            'par_lien' => $links->map(fn ($link) => [
                'id' => $link->id,
                'code' => $link->code,
                'nom' => $link->nom,
                'actif' => $link->actif,
                'ouvertures' => $link->visits_count,
                'soumissions' => $link->dossiers_count,
            ]),
            'dernieres_adhesions' => AdherentDossier::query()->latest()->limit(5)->get()->map(fn ($dossier) => [
                'id' => $dossier->id,
                'numero' => $dossier->numero,
                'civilite' => $dossier->civilite,
                'nom' => $dossier->nom,
                'prenoms' => $dossier->prenoms,
                'matricule' => $dossier->matricule,
                'created_at' => $dossier->created_at,
                'signature' => $dossier->signature_path !== null,
            ]),
        ]);
    }
}
