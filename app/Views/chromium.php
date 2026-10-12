<!DOCTYPE html>
<?php /** @var string $executable */ ?>
<html lang="en" class="dark">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chromium Profiles - Dolphin{anty} Monitoring</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#0b0f19',
                            800: '#111827',
                            700: '#1f2937',
                            600: '#374151'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-dark-900 text-gray-100 min-h-screen flex antialiased">
    <?= view('partials/sidebar_tailwind') ?>
    <main class="flex-1 ml-20 lg:ml-24 p-6 lg:p-10">
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-1">
                    <i class="bi bi-browser-chrome"></i> Local Chromium
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    Chromium Profiles
                    <span id="totalBadge" class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-medium">0 Total</span>
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <input type="text" id="searchBox" autocomplete="off" placeholder="Search profiles..." class="px-4 py-2 rounded-lg bg-dark-800 border border-gray-700 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                    <i class="bi bi-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-600"></i>
                </div>
                <button type="button" id="refreshProfiles" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">
                    <i class="bi bi-arrow-clockwise"></i> REFRESH
                </button>
            </div>
        </header>

        <div id="notice" class="hidden mb-5 rounded-xl border px-4 py-3 text-sm" role="status"></div>

        <section class="mb-6 rounded-2xl border border-gray-800 bg-dark-800/90 p-5 shadow-xl">
            <h2 class="text-sm font-semibold text-white">Chromium executable</h2>
            <p class="mt-1 text-xs text-gray-500">Default: folder proyek <code class="text-cyan-300">chromium\chromedriver.exe</code>. Path dapat diarahkan ke executable Chromium lain.</p>
            <form id="executableForm" class="mt-4 flex flex-col sm:flex-row gap-3">
                <input id="executablePath" type="text" value="<?= esc($executable, 'attr') ?>" autocomplete="off" class="min-w-0 flex-1 rounded-xl border border-gray-700 bg-dark-900 px-4 py-3 text-sm font-mono text-gray-200 focus:border-cyan-500 focus:outline-none">
                <button class="rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white hover:bg-cyan-500"><i class="bi bi-save mr-1"></i> Save path</button>
            </form>
        </section>

        <section class="mb-6 rounded-2xl border border-gray-800 bg-dark-800/90 p-5 shadow-xl">
            <h2 class="text-sm font-semibold text-white">Create profile</h2>
            <form id="createForm" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input id="newProfileName" type="text" maxlength="255" required placeholder="Profile name" class="rounded-xl border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none">
                <select id="newProxyId" class="rounded-xl border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none">
                    <option value="none">No proxy</option>
                </select>
                <select id="newPlaylistId" class="rounded-xl border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none">
                    <option value="none">No playlist</option>
                </select>
                <button class="rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-semibold text-white"><i class="bi bi-plus-lg mr-1"></i> Create</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-800 bg-dark-800/90 shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-800 px-5 py-4">
                <div>
                    <h2 class="font-semibold text-white">Profiles</h2>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[940px] text-left text-sm">
                    <thead class="bg-dark-900/70 text-[11px] uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Profile</th>
                            <th class="px-4 py-3">Proxy</th>
                            <th class="px-4 py-3">Playlist</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profilesBody" class="divide-y divide-gray-800">
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-gray-500"><i class="bi bi-arrow-repeat animate-spin text-cyan-400"></i> Loading profiles...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <p class="mt-5 text-xs leading-5 text-gray-500">Ekstensi BayzBot dimuat otomatis dan ID profil Chromium diatur terpisah dari ID Dolphin. Proxy dengan autentikasi menggunakan kredensial tersimpan pada halaman Proxies.</p>
    </main>
    <script>
        const baseUrl = <?= json_encode(base_url()) ?>;
        const profilesBody = document.getElementById('profilesBody');
        let profiles = [];
        let proxies = [];
        let playlists = [];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[character]);
        }

        function notice(message, success = false) {
            const element = document.getElementById('notice');
            element.textContent = message;
            element.className = `mb-5 rounded-xl border px-4 py-3 text-sm ${success ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300'}`;
        }

        async function post(path, data = {}) {
            const response = await fetch(`${baseUrl}${path}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(data)
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success) throw new Error(result.message || `Request failed (HTTP ${response.status})`);
            return result;
        }

        function fillSelect(select, values, emptyLabel, selected = 'none') {
            select.innerHTML = `<option value="none">${escapeHtml(emptyLabel)}</option>` + values.map(item =>
                `<option value="${item.id}" ${String(item.id) === String(selected) ? 'selected' : ''}>${escapeHtml(item.label)}</option>`
            ).join('');
        }

        async function loadOptions() {
            const [proxyResponse, playlistResponse] = await Promise.all([
                fetch(`${baseUrl}api/chromium/proxies`).then(response => response.json()),
                fetch(`${baseUrl}api/playlist/all`).then(response => response.json())
            ]);
            if (!proxyResponse.success || !playlistResponse.success) throw new Error('Proxy atau playlist gagal dimuat.');
            proxies = proxyResponse.data.map(proxy => ({
                id: proxy.id,
                label: `${proxy.name}${proxy.country ? ` · ${proxy.country}` : ''}${proxy.has_auth ? ' · auth' : ''}`
            }));
            playlists = playlistResponse.data.map(playlist => ({
                id: playlist.id,
                label: playlist.playlist_name
            }));
            fillSelect(document.getElementById('newProxyId'), proxies, 'No proxy');
            fillSelect(document.getElementById('newPlaylistId'), playlists, 'No playlist');
        }

        async function loadProfiles() {
            profilesBody.innerHTML = '<tr><td colspan="5" class="px-5 py-12 text-center text-gray-500"><i class="bi bi-arrow-repeat animate-spin text-cyan-400"></i> Loading profiles...</td></tr>';
            const response = await fetch(`${baseUrl}api/chromium/profiles`);
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success) throw new Error(result.message || 'Chromium profiles failed to load.');
            profiles = result.data;
            document.getElementById('totalBadge').textContent = `${profiles.length} Total`;
            renderProfiles();
        }

        function renderProfiles() {
            if (!profiles.length) {
                profilesBody.innerHTML = '<tr><td colspan="5" class="px-5 py-12 text-center text-gray-500">Belum ada profil Chromium. Buat profil untuk memulai.</td></tr>';
                return;
            }
            profilesBody.innerHTML = profiles.map(profile => `
                <tr data-id="${profile.id}" class="hover:bg-dark-900/50 transition-colors">
                    <td class="px-5 py-4">
                        <input data-field="profile_name" value="${escapeHtml(profile.profile_name)}" maxlength="255" class="w-48 rounded-lg border border-gray-700 bg-dark-900 px-3 py-2 text-sm text-gray-200">
                        <div class="mt-1 text-[10px] text-gray-500">BayzBot ID: ${profile.bridge_id}</div>
                    </td>
                    <td class="px-4 py-4"><select data-field="proxy_id" class="w-48 rounded-lg border border-gray-700 bg-dark-900 px-3 py-2 text-xs text-gray-200">${optionsHtml(proxies, profile.proxy_id)}</select></td>
                    <td class="px-4 py-4"><select data-field="playlist_id" class="w-48 rounded-lg border border-gray-700 bg-dark-900 px-3 py-2 text-xs text-gray-200">${optionsHtml(playlists, profile.playlist_id)}</select></td>
                    <td class="px-4 py-4"><span class="rounded-full border px-2.5 py-1 text-xs ${profile.running ? 'border-emerald-500/30 bg-emerald-500/10 text-cyan-400' : 'border-gray-700 bg-dark-900 text-gray-400'}">${profile.running ? 'Running' : 'Stopped'}</span></td>
                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        <button data-action="save" class="rounded-lg border border-gray-700 px-3 py-2 text-xs font-semibold text-cyan-300 hover:border-cyan-500">Save</button>
                        <button data-action="${profile.running ? 'stop' : 'start'}" class="ml-1 rounded-lg border px-3 py-2 text-xs font-semibold ${profile.running ? 'border-rose-500/30 text-rose-300 hover:bg-rose-500/10' : 'border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/10'}">${profile.running ? 'Close' : 'Open'}</button>
                        <button data-action="delete" class="ml-1 rounded-lg border border-gray-700 px-3 py-2 text-xs font-semibold text-gray-400 hover:border-rose-500 hover:text-rose-300">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        function optionsHtml(items, selected) {
            return `<option value="none">None</option>` + items.map(item =>
                `<option value="${item.id}" ${String(item.id) === String(selected ?? '') ? 'selected' : ''}>${escapeHtml(item.label)}</option>`
            ).join('');
        }

        document.getElementById('executableForm').addEventListener('submit', async event => {
            event.preventDefault();
            try {
                const result = await post('api/chromium/executable', {
                    executable: document.getElementById('executablePath').value.trim()
                });
                notice(result.message, true);
            } catch (error) {
                notice(error.message);
            }
        });

        document.getElementById('createForm').addEventListener('submit', async event => {
            event.preventDefault();
            try {
                const result = await post('api/chromium/profiles', {
                    profile_name: document.getElementById('newProfileName').value.trim(),
                    proxy_id: document.getElementById('newProxyId').value,
                    playlist_id: document.getElementById('newPlaylistId').value
                });
                document.getElementById('createForm').reset();
                notice(result.message, true);
                await loadProfiles();
            } catch (error) {
                notice(error.message);
            }
        });

        profilesBody.addEventListener('click', async event => {
            const button = event.target.closest('button[data-action]');
            if (!button) return;
            const row = button.closest('tr');
            const id = row.dataset.id;
            const action = button.dataset.action;
            try {
                if (action === 'save') {
                    const fields = Object.fromEntries(Array.from(row.querySelectorAll('[data-field]')).map(input => [input.dataset.field, input.value]));
                    const result = await post(`api/chromium/profiles/${id}/update`, fields);
                    notice(result.message, true);
                } else if (action === 'delete') {
                    if (!confirm('Hapus profil ini beserta semua cookies dan data lokalnya?')) return;
                    const result = await post(`api/chromium/profiles/${id}/delete`);
                    notice(result.message, true);
                } else {
                    const result = await post(`api/chromium/profiles/${id}/${action}`);
                    notice(result.message, true);
                }
                await loadProfiles();
            } catch (error) {
                notice(error.message);
            }
        });

        document.getElementById('refreshProfiles').addEventListener('click', () => {
            Promise.all([loadOptions(), loadProfiles()]).catch(error => notice(error.message));
        });

        Promise.all([loadOptions(), loadProfiles()]).catch(error => notice(error.message));
        setInterval(() => loadProfiles().catch(error => console.error(error)), 10000);
    </script>
</body>

</html>