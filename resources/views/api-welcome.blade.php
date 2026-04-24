<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>SOS-Expat · Partner Engine API</title>
    <link rel="icon" type="image/webp" href="https://sos-expat.com/sos-logo.webp">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .sos-gradient { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); }
        code { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.875em; }
    </style>
</head>
<body class="min-h-screen bg-white text-slate-900">

    <header class="sos-gradient text-white py-4 px-6 shadow-md">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-white rounded-full p-1 shadow-sm">
                    <img src="https://sos-expat.com/sos-logo.webp" alt="SOS-Expat" class="h-9 w-9 object-contain">
                </div>
                <div>
                    <div class="font-bold text-lg tracking-tight">SOS-Expat</div>
                    <div class="text-xs text-white/90">Partner Engine · REST API</div>
                </div>
            </div>
            <div class="text-xs text-white/80 hidden sm:block">
                B2B partner integrations
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-10 sm:py-16">

        <section class="mb-10">
            <h1 class="text-3xl sm:text-4xl font-bold">Partner Engine API</h1>
            <p class="mt-3 text-slate-600 max-w-2xl">
                This subdomain hosts the REST API that powers SOS-Expat's B2B partner integrations —
                subscriber provisioning, SOS-Call activation, billing webhooks, and activity reporting.
                It is not a user-facing site.
            </p>
        </section>

        <section class="mb-10">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Where to go</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <a href="https://sos-expat.com" class="block p-4 rounded-xl border border-slate-200 hover:border-sos-red hover:shadow-md transition group">
                    <div class="font-semibold text-slate-900 group-hover:text-red-700">SOS-Expat main site</div>
                    <div class="text-sm text-slate-500 mt-1">Public site, clients, partners, affiliates.</div>
                </a>
                <a href="https://sos-expat.com/partner/tableau-de-bord" class="block p-4 rounded-xl border border-slate-200 hover:border-red-500 hover:shadow-md transition group">
                    <div class="font-semibold text-slate-900 group-hover:text-red-700">Partner dashboard</div>
                    <div class="text-sm text-slate-500 mt-1">Your subscribers, invoices, SOS-Call activity.</div>
                </a>
                <a href="https://admin.sos-expat.com" class="block p-4 rounded-xl border border-slate-200 hover:border-red-500 hover:shadow-md transition group">
                    <div class="font-semibold text-slate-900 group-hover:text-red-700">Admin console (Filament)</div>
                    <div class="text-sm text-slate-500 mt-1">SOS-Expat staff only.</div>
                </a>
                <a href="https://sos-call.sos-expat.com" class="block p-4 rounded-xl border border-slate-200 hover:border-red-500 hover:shadow-md transition group">
                    <div class="font-semibold text-slate-900 group-hover:text-red-700">SOS-Call activation page</div>
                    <div class="text-sm text-slate-500 mt-1">For clients of partners who have a code.</div>
                </a>
            </div>
        </section>

        <section class="mb-10">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Public endpoints</h2>
            <div class="rounded-xl border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium">Method</th>
                            <th class="text-left px-4 py-2 font-medium">Path</th>
                            <th class="text-left px-4 py-2 font-medium">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-4 py-2"><code class="bg-green-50 text-green-700 px-2 py-0.5 rounded">GET</code></td>
                            <td class="px-4 py-2"><a href="/api/health" class="text-red-700 hover:underline"><code>/api/health</code></a></td>
                            <td class="px-4 py-2 text-slate-600">Service status (DB, Redis, version)</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2"><code class="bg-green-50 text-green-700 px-2 py-0.5 rounded">GET</code></td>
                            <td class="px-4 py-2"><a href="/api/health/detailed" class="text-red-700 hover:underline"><code>/api/health/detailed</code></a></td>
                            <td class="px-4 py-2 text-slate-600">Detailed metrics (admin auth)</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2"><code class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded">POST</code></td>
                            <td class="px-4 py-2"><code>/api/sos-call/check</code></td>
                            <td class="px-4 py-2 text-slate-600">Rate-limited SOS-Call eligibility check (code or phone+email)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-10">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Authenticated endpoints</h2>
            <p class="text-slate-600 text-sm mb-3">
                All <code>/api/partner/*</code>, <code>/api/admin/*</code>, <code>/api/subscriber/*</code>
                endpoints require a Firebase ID token:
            </p>
            <pre class="bg-slate-900 text-slate-100 text-xs rounded-xl p-4 overflow-x-auto">Authorization: Bearer &lt;firebase_id_token&gt;</pre>

            <p class="text-slate-600 text-sm mt-6 mb-3">
                Server-to-server <code>/api/v1/partner/*</code> endpoints use a static API key:
            </p>
            <pre class="bg-slate-900 text-slate-100 text-xs rounded-xl p-4 overflow-x-auto">Authorization: Bearer pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx</pre>
        </section>

        <section class="text-center text-sm text-slate-500 mt-16">
            <a href="/api/health" class="text-red-700 hover:underline font-medium">View service status →</a>
        </section>
    </main>

    <footer class="max-w-4xl mx-auto px-4 py-8 text-center text-xs text-slate-400 border-t border-slate-100 mt-12">
        <p>© SOS-Expat · Partner Engine API</p>
    </footer>

</body>
</html>
