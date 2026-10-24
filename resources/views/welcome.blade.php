<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Synergie CI — API Service</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <!-- Tailwind CSS via CDN pour un rendu rapide et moderne -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- En-tête -->
    <header class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md px-6 py-4">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/20">
                    S
                </div>
                <span class="font-semibold text-sm tracking-wide text-white uppercase">Synergie CI — Adhésion</span>
            </div>

            <!-- Badge Statut -->
            <div class="flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 rounded-full text-xs font-medium text-emerald-400">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Opérationnel
            </div>
        </div>
    </header>

    <!-- Contenu Principal -->
    <main class="max-w-5xl mx-auto px-6 py-16 flex-1 flex flex-col justify-center w-full">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-400 bg-indigo-950/60 border border-indigo-800/50 px-3 py-1 rounded-md">
                Service API REST
            </span>
            <h1 class="text-4xl sm:text-5xl font-extrabold text-white mt-4 tracking-tight">
                Plateforme API Adhésion
            </h1>
            <p class="mt-4 text-slate-400 text-sm sm:text-base leading-relaxed">
                Point d'accès aux services backend et à l'authentification de Synergie CI.
            </p>
        </div>

        <!-- Cartes d'informations -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5">
                <span class="text-xs text-slate-400 uppercase font-medium">Environnement</span>
                <p class="text-lg font-semibold text-white mt-1">Production</p>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5">
                <span class="text-xs text-slate-400 uppercase font-medium">Version API</span>
                <p class="text-lg font-semibold text-white mt-1">v1.0.0</p>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5">
                <span class="text-xs text-slate-400 uppercase font-medium">Base URL</span>
                <p class="text-sm font-mono text-indigo-300 mt-2 truncate">https://api.synergieci.org/api</p>
            </div>
        </div>

        <!-- Bloc Exemple de Requête -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between px-4 py-3 bg-slate-950/70 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-rose-500/60"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-500/60"></div>
                    <div class="w-3 h-3 rounded-full bg-emerald-500/60"></div>
                    <span class="text-xs text-slate-400 ml-2 font-mono">Exemple de requête</span>
                </div>
                <span class="text-xs text-slate-400 font-mono">JSON</span>
            </div>
            <pre class="p-5 text-xs sm:text-sm font-mono text-slate-300 overflow-x-auto leading-relaxed"><code><span class="text-slate-400">// Healthcheck response</span>
{
  <span class="text-indigo-400">"status"</span>: <span class="text-emerald-400">"success"</span>,
  <span class="text-indigo-400">"message"</span>: <span class="text-emerald-400">"Synergie CI API is running"</span>,
  <span class="text-indigo-400">"timestamp"</span>: <span class="text-amber-300">"{{ now()->toIso8601String() }}"</span>
}</code></pre>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="border-t border-slate-800/80 px-6 py-6 text-center text-xs text-slate-400">
        &copy; {{ date('Y') }} Synergie CI. Tous droits réservés.
    </footer>

</body>
</html>
