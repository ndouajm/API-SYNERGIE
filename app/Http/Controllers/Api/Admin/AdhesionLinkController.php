<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdhesionLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdhesionLinkController extends Controller
{
    public function index(): JsonResponse
    {
        $links = AdhesionLink::query()
            ->withCount(['visits', 'dossiers'])
            ->latest()
            ->get();

        return response()->json(['data' => $links]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        do {
            $code = Str::upper(Str::random(8));
        } while (AdhesionLink::where('code', $code)->exists());

        $link = AdhesionLink::create([
            'code' => $code,
            'nom' => trim($data['nom']),
            'actif' => true,
        ]);

        return response()->json(['data' => $link], 201);
    }

    public function update(Request $request, AdhesionLink $adhesionLink): JsonResponse
    {
        $data = $request->validate([
            'actif' => ['required', 'boolean'],
        ]);
        $adhesionLink->update($data);

        return response()->json(['data' => $adhesionLink->fresh()]);
    }
}
