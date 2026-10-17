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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-dark-900 text-gray-100 min-h-screen flex antialiased">
    <?= view('partials/sidebar_tailwind') ?>

    <!-- Main Content -->
    <main class="flex-1 ml-20 lg:ml-24 p-6 lg:p-10">
        <!-- Header -->
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
                <button type="button" onclick="openExecutableModal()" class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-dark-800 border border-gray-700 text-gray-300 hover:border-cyan-500 hover:text-cyan-300 transition-colors" title="Chromium executable" aria-label="Chromium executable">
                    <i class="bi bi-gear"></i>
                </button>
                <button type="button" onclick="openProfileModal(null)" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-dark-800 border border-cyan-500/40 text-cyan-300 font-medium text-sm hover:bg-cyan-500/10 transition-colors">
                    <i class="bi bi-plus-lg"></i> NEW PROFILE
                </button>
                <button type="button" id="refreshBtn" onclick="refreshAll()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">
                    <i class="bi bi-arrow-clockwise"></i> REFRESH
                </button>
            </div>
        </header>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                    <i class="bi bi-browser-chrome text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Profiles</p>
                    <p id="statTotal" class="text-2xl font-bold text-white">0</p>
                </div>
            </div>
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                    <i class="bi bi-play-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Running</p>
                    <p id="statRunning" class="text-2xl font-bold text-white">0</p>
                </div>
            </div>
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gray-500/10 text-gray-400 flex items-center justify-center border border-gray-700">
                    <i class="bi bi-stop-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Stopped</p>
                    <p id="statStopped" class="text-2xl font-bold text-white">0</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div id="filterTabs" class="inline-flex gap-2 bg-dark-800 border border-gray-800 rounded-xl p-1">
                <button data-filter="all" class="px-4 py-2 rounded-lg text-sm font-medium">All</button>
                <button data-filter="running" class="px-4 py-2 rounded-lg text-sm font-medium">Running</button>
                <button data-filter="stopped" class="px-4 py-2 rounded-lg text-sm font-medium">Stopped</button>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Auto-refresh every 10s
            </div>
        </div>

        <!-- Profiles Table -->
        <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <p class="px-4 py-2 text-[11px] text-gray-500 sm:hidden">Geser tabel ke samping untuk melihat semua kolom.</p>
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="w-full min-w-[980px]">
                    <thead class="border-b border-gray-800 bg-dark-900/50">
                        <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <th class="w-14 px-4 py-4 text-left"><input type="checkbox" id="selectAll" class="w-5 h-5 rounded border-2 border-gray-600 text-cyan-500 bg-dark-800 cursor-pointer focus:ring-2 focus:ring-cyan-500 focus:ring-offset-0 hover:border-gray-500 transition-colors"></th>
                            <th class="w-64 px-4 py-4 text-left">Name</th>
                            <th class="w-40 px-4 py-4 text-left">Instance</th>
                            <th class="w-64 px-4 py-4 text-left">Proxy</th>
                            <th class="w-56 px-4 py-4 text-left">Playlist</th>
                            <th class="w-28 px-4 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profilesList" class="divide-y divide-gray-800">
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="bi bi-arrow-repeat text-3xl animate-spin inline-block opacity-40"></i>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="pager" class="hidden flex items-center justify-between px-6 py-4 border-t border-gray-800 bg-dark-900/50">
                <span id="pagerInfo" class="text-xs text-gray-400"></span>
                <div class="flex gap-2">
                    <button id="prevPage" onclick="goPage(-1)" class="px-4 py-2 rounded-lg bg-dark-700 text-gray-300 text-sm font-medium hover:bg-dark-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"><i class="bi bi-chevron-left"></i> Prev</button>
                    <button id="nextPage" onclick="goPage(1)" class="px-4 py-2 rounded-lg bg-dark-700 text-gray-300 text-sm font-medium hover:bg-dark-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Next <i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <p class="mt-5 text-xs leading-5 text-gray-500">Ekstensi BayzBot dimuat otomatis dan ID profil Chromium diatur terpisah dari ID Dolphin. Proxy dengan autentikasi menggunakan kredensial tersimpan pada halaman Proxies.</p>

        <!-- Bulk Actions Bar -->
        <div id="bulkActions" class="hidden fixed bottom-8 left-1/2 -translate-x-1/2 bg-blue-600 shadow-2xl rounded-2xl px-6 py-4 flex items-center gap-4 z-50">
            <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-lg">
                <span id="selectedCount" class="text-lg font-bold text-white">0</span>
            </div>
            <div class="h-8 w-px bg-white/20"></div>
            <button onclick="clearSelection()" class="p-2 hover:bg-white/10 rounded-lg transition-colors" title="Clear selection"><i class="bi bi-arrow-counterclockwise text-white text-xl"></i></button>
            <button onclick="bulkAction('start')" class="p-2 hover:bg-white/10 rounded-lg transition-colors" title="Start selected"><i class="bi bi-play-fill text-white text-xl"></i></button>
            <button onclick="bulkAction('stop')" class="p-2 hover:bg-white/10 rounded-lg transition-colors" title="Stop selected"><i class="bi bi-stop-fill text-white text-xl"></i></button>
        </div>

        <!-- Profile Modal (create / edit) -->
        <div id="profileModal" class="hidden fixed inset-0 z-[85] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle" onclick="if (event.target === this) closeProfileModal()">
            <form id="profileForm" class="w-full max-w-lg overflow-hidden rounded-2xl border border-gray-700 bg-dark-800 text-gray-100 shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-700 px-5 py-5 sm:px-8">
                    <div>
                        <h2 id="profileModalTitle" class="text-xl font-bold text-white">New profile</h2>
                        <p id="profileModalSubtitle" class="mt-1 text-xs text-gray-400"></p>
                    </div>
                    <button type="button" onclick="closeProfileModal()" aria-label="Close" class="text-gray-400 transition-colors hover:text-white"><i class="bi bi-x-lg text-lg"></i></button>
                </div>
                <div class="space-y-4 p-5 sm:p-6">
                    <div>
                        <label for="profileName" class="block text-sm text-gray-400 mb-1.5">Profile name</label>
                        <input id="profileName" type="text" maxlength="255" required autocomplete="off" class="w-full rounded-lg border border-gray-700 bg-dark-900 px-3 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                    </div>
                    <div>
                        <label for="profileProxy" class="block text-sm text-gray-400 mb-1.5">Proxy</label>
                        <select id="profileProxy" class="w-full rounded-lg border border-gray-700 bg-dark-900 px-3 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            <option value="none">No proxy</option>
                        </select>
                    </div>
                    <div>
                        <label for="profilePlaylist" class="block text-sm text-gray-400 mb-1.5">Playlist</label>
                        <select id="profilePlaylist" class="w-full rounded-lg border border-gray-700 bg-dark-900 px-3 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            <option value="none">No playlist</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-700 bg-dark-900/50 px-5 py-4 sm:px-6">
                    <button type="button" onclick="closeProfileModal()" class="rounded-lg bg-dark-700 px-5 py-3 text-sm font-medium text-gray-300 hover:bg-dark-600">CANCEL</button>
                    <button id="profileSaveBtn" type="submit" class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-cyan-500/20 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50">SAVE</button>
                </div>
            </form>
        </div>

        <!-- Executable Modal -->
        <div id="executableModal" class="hidden fixed inset-0 z-[85] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="executableModalTitle" onclick="if (event.target === this) closeExecutableModal()">
            <form id="executableForm" class="w-full max-w-lg overflow-hidden rounded-2xl border border-gray-700 bg-dark-800 text-gray-100 shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-700 px-5 py-5 sm:px-8">
                    <div>
                        <h2 id="executableModalTitle" class="text-xl font-bold text-white">Chromium executable</h2>
                        <p class="mt-1 text-xs text-gray-400">Default: folder proyek <code class="text-cyan-300">chromium\chromedriver.exe</code>. Path dapat diarahkan ke executable Chromium lain.</p>
                    </div>
                    <button type="button" onclick="closeExecutableModal()" aria-label="Close" class="text-gray-400 transition-colors hover:text-white"><i class="bi bi-x-lg text-lg"></i></button>
                </div>
                <div class="space-y-3 p-5 sm:p-6">
                    <label for="executablePath" class="block text-sm text-gray-400">Executable path</label>
                    <input id="executablePath" type="text" value="<?= esc($executable, 'attr') ?>" autocomplete="off" class="w-full rounded-lg border border-gray-700 bg-dark-900 px-3 py-3 text-sm font-mono text-gray-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-700 bg-dark-900/50 px-5 py-4 sm:px-6">
                    <button type="button" onclick="closeExecutableModal()" class="rounded-lg bg-dark-700 px-5 py-3 text-sm font-medium text-gray-300 hover:bg-dark-600">CANCEL</button>
                    <button id="executableSaveBtn" type="submit" class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-cyan-500/20 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50">SAVE PATH</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const baseUrl = <?= json_encode(base_url()) ?>;
        const PAGE_SIZE = 10;
        const state = {
            profiles: [],
            proxies: [],
            playlists: [],
            filter: 'all',
            search: '',
            page: 1,
            selected: new Set(),
            busy: new Set(),
            bulkRunning: false,
            editingId: null
        };
        let countryCodeMap = null;

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[character]);
        }

        // ---- Alert (toast sama seperti halaman Browser) ----
        function showAlert(message, type = 'success') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: toast => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            Toast.fire({
                icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                title: message,
                background: '#111827',
                color: '#fff'
            });
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

        function getCountryCode(country) {
            const raw = String(country || '').trim();
            if (!raw || raw.toLowerCase() === 'unknown') return '';
            if (/^[A-Za-z]{2}$/.test(raw)) return raw.toLowerCase();

            if (!countryCodeMap) {
                countryCodeMap = {
                    'united states': 'us', 'usa': 'us', 'united kingdom': 'gb', 'uk': 'gb', 'hong kong': 'hk',
                    'macau': 'mo', 'south korea': 'kr', 'north korea': 'kp', 'russia': 'ru', 'vietnam': 'vn',
                    'turkey': 'tr', 'czech republic': 'cz', 'taiwan': 'tw', 'moldova': 'md', 'laos': 'la',
                    'iran': 'ir', 'syria': 'sy', 'bolivia': 'bo', 'venezuela': 've', 'tanzania': 'tz',
                    'brunei': 'bn', 'palestine': 'ps', 'ivory coast': 'ci', 'myanmar': 'mm', 'kosovo': 'xk'
                };
                const legacyCodes = new Set(['AN', 'BU', 'CS', 'DD', 'DY', 'FX', 'HV', 'NH', 'RH', 'SU', 'TP', 'UK', 'VD', 'YD', 'YU', 'ZR']);
                try {
                    const names = new Intl.DisplayNames(['en'], {
                        type: 'region'
                    });
                    for (let first = 65; first <= 90; first++) {
                        for (let second = 65; second <= 90; second++) {
                            const code = String.fromCharCode(first, second);
                            if (legacyCodes.has(code)) continue;
                            const name = names.of(code);
                            if (name && name !== code && !(name.toLowerCase() in countryCodeMap)) {
                                countryCodeMap[name.toLowerCase()] = code.toLowerCase();
                            }
                        }
                    }
                } catch (error) {}
            }
            return countryCodeMap[raw.toLowerCase()] || '';
        }

        function fillSelect(select, values, emptyLabel, selected = 'none') {
            select.innerHTML = `<option value="none">${esc(emptyLabel)}</option>` + values.map(item =>
                `<option value="${item.id}" ${String(item.id) === String(selected) ? 'selected' : ''}>${esc(item.label)}</option>`
            ).join('');
        }

        // ---- Data ----
        async function loadOptions() {
            const [proxyResponse, playlistResponse] = await Promise.all([
                fetch(`${baseUrl}api/chromium/proxies`).then(response => response.json()),
                fetch(`${baseUrl}api/playlist/all`).then(response => response.json())
            ]);
            if (!proxyResponse.success || !playlistResponse.success) throw new Error('Proxy atau playlist gagal dimuat.');
            state.proxies = proxyResponse.data.map(proxy => ({
                id: proxy.id,
                name: proxy.name,
                country: proxy.country || '',
                label: `${proxy.name}${proxy.country ? ` · ${proxy.country}` : ''}${proxy.has_auth ? ' · auth' : ''}`
            }));
            state.playlists = playlistResponse.data.map(playlist => ({
                id: playlist.id,
                label: playlist.playlist_name
            }));
        }

        async function loadProfiles(silent = false) {
            const response = await fetch(`${baseUrl}api/chromium/profiles`);
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success) throw new Error(result.message || 'Chromium profiles failed to load.');
            state.profiles = result.data;
            const ids = new Set(state.profiles.map(profile => Number(profile.id)));
            state.selected.forEach(id => { if (!ids.has(id)) state.selected.delete(id); });
            render();
        }

        function refreshAll() {
            // render ulang setelah proxy/playlist termuat agar nama playlist & negara proxy tampil
            Promise.all([loadOptions(), loadProfiles()]).then(render).catch(error => showAlert(error.message, 'error'));
        }

        // ---- Render ----
        function proxyOf(profile) {
            return state.proxies.find(proxy => String(proxy.id) === String(profile.proxy_id ?? '')) || null;
        }

        function playlistOf(profile) {
            return state.playlists.find(playlist => String(playlist.id) === String(profile.playlist_id ?? '')) || null;
        }

        function visibleProfiles() {
            const query = state.search.toLowerCase();
            return state.profiles.filter(profile => {
                if (state.filter === 'running' && !profile.running) return false;
                if (state.filter === 'stopped' && profile.running) return false;
                if (!query) return true;
                const proxy = proxyOf(profile);
                const playlist = playlistOf(profile);
                return [profile.profile_name, profile.id, profile.bridge_id, profile.proxy_name, proxy?.country, playlist?.label]
                    .some(value => String(value ?? '').toLowerCase().includes(query));
            });
        }

        function pageCount(total) {
            return Math.max(1, Math.ceil(total / PAGE_SIZE));
        }

        function render() {
            const running = state.profiles.filter(profile => profile.running).length;
            const total = state.profiles.length;
            document.getElementById('statTotal').textContent = total;
            document.getElementById('statRunning').textContent = running;
            document.getElementById('statStopped').textContent = total - running;
            document.getElementById('totalBadge').textContent = `${total} Total`;

            document.querySelectorAll('#filterTabs button').forEach(button => {
                const active = button.dataset.filter === state.filter;
                button.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors ' +
                    (active ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' : 'text-gray-400 hover:text-gray-200');
            });

            const rows = visibleProfiles();
            state.page = Math.min(state.page, pageCount(rows.length));
            const pageRows = rows.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE);
            const list = document.getElementById('profilesList');
            if (pageRows.length === 0) {
                list.innerHTML = `
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="bi bi-browser-chrome text-4xl mb-3 opacity-20"></i>
                            <p class="text-sm">${state.search || state.filter !== 'all' ? 'No profiles match your filter.' : 'Belum ada profil Chromium. Klik NEW PROFILE untuk memulai.'}</p>
                        </div>
                    </td></tr>`;
            } else {
                list.innerHTML = pageRows.map(rowHtml).join('');
            }
            renderPager(rows.length);
            updateBulkActions();
        }

        function instanceButton(profile) {
            const id = Number(profile.id);
            if (state.busy.has(id)) {
                return `<button disabled class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-gray-600 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-wait"><i class="bi bi-arrow-repeat animate-spin inline-block"></i></button>`;
            }
            if (profile.running) {
                return `<button onclick="runAction(${id}, 'stop')" class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-rose-500 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-rose-400 hover:bg-rose-500/10 transition-colors" title="Stop profile"><i class="bi bi-stop-fill"></i> Stop</button>`;
            }
            return `<button onclick="runAction(${id}, 'start')" class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-emerald-500 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-emerald-400 hover:bg-emerald-500/10 transition-colors" title="Open Chromium profile"><i class="bi bi-play-fill"></i> Start</button>`;
        }

        function rowHtml(profile) {
            const id = Number(profile.id);
            const proxy = proxyOf(profile);
            const playlist = playlistOf(profile);
            const proxyName = profile.proxy_name || proxy?.name || '';
            const countryCode = getCountryCode(proxy?.country);
            return `
            <tr class="hover:bg-dark-700/50 transition-colors" data-id="${id}">
                <td class="px-4 py-4"><input type="checkbox" ${state.selected.has(id) ? 'checked' : ''} class="w-5 h-5 rounded border-2 border-gray-600 text-cyan-500 bg-dark-800 cursor-pointer focus:ring-2 focus:ring-cyan-500 focus:ring-offset-0 hover:border-gray-500 transition-colors"></td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-browser-chrome text-blue-500 text-lg" title="Chromium"></i>
                        <span class="text-cyan-400 font-medium">${esc(profile.profile_name)}</span>
                        <button onclick="copyId(${id})" class="text-gray-400 hover:text-gray-200 transition-colors" title="Copy profile ID"><i class="bi bi-copy"></i></button>
                    </div>
                    <div class="text-xs text-gray-400 mt-1 flex items-center gap-1.5">
                        <span class="font-mono">ID ${id}</span>
                        <span class="text-gray-500">•</span>
                        <span>BayzBot ID ${esc(profile.bridge_id)}</span>
                    </div>
                </td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-3">${instanceButton(profile)}</div>
                </td>
                <td class="px-4 py-4 text-sm">
                    <div class="flex min-w-48 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="break-all font-mono text-gray-300">${proxyName ? esc(proxyName) : '<span class="text-gray-500">-</span>'}</div>
                            ${proxy && proxy.country ? `<div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">${countryCode ? `<span class="fi fi-${esc(countryCode)} rounded-sm"></span>` : ''}<span>${esc(proxy.country)}</span></div>` : ''}
                        </div>
                        <button onclick="openProfileModal(${id}, 'proxy')" class="shrink-0 rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-gray-300 hover:border-cyan-500 hover:text-cyan-300" title="Change proxy" aria-label="Change proxy"><i class="bi bi-pencil"></i></button>
                    </div>
                </td>
                <td class="px-4 py-4 text-sm">
                    <div class="flex min-w-40 items-center justify-between gap-2">
                        <span class="truncate text-gray-300" title="${esc(playlist?.label || 'No playlist')}">${esc(playlist?.label || '-')}</span>
                        <button onclick="openProfileModal(${id}, 'playlist')" class="shrink-0 rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-gray-300 hover:border-cyan-500 hover:text-cyan-300" title="Change playlist" aria-label="Change playlist"><i class="bi bi-pencil"></i></button>
                    </div>
                </td>
                <td class="px-4 py-4 text-right whitespace-nowrap">
                    <button onclick="openProfileModal(${id})" class="rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-gray-300 hover:border-cyan-500 hover:text-cyan-300" title="Edit profile" aria-label="Edit profile"><i class="bi bi-sliders"></i></button>
                    <button onclick="deleteProfile(${id})" class="ml-1 rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-gray-400 hover:border-rose-500 hover:text-rose-300" title="Delete profile" aria-label="Delete profile"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
        }

        function renderPager(totalRows) {
            const pager = document.getElementById('pager');
            const last = pageCount(totalRows);
            if (last <= 1) {
                pager.classList.add('hidden');
                return;
            }
            pager.classList.remove('hidden');
            document.getElementById('pagerInfo').textContent = `Page ${state.page} of ${last}`;
            document.getElementById('prevPage').disabled = state.page <= 1;
            document.getElementById('nextPage').disabled = state.page >= last;
        }

        function goPage(delta) {
            state.page = Math.max(1, state.page + delta);
            render();
        }

        // ---- Actions ----
        async function runAction(id, action) {
            state.busy.add(id);
            render();
            try {
                const result = await post(`api/chromium/profiles/${id}/${action}`);
                showAlert(result.message || `Profile ${id} ${action === 'start' ? 'started' : 'stopped'}`, 'success');
            } catch (error) {
                showAlert(error.message, 'error');
            }
            await loadProfiles(true).catch(() => {});
            state.busy.delete(id);
            render();
        }

        function bulkAction(action) {
            const ids = Array.from(state.selected);
            if (ids.length === 0 || state.bulkRunning) return;

            Swal.fire({
                title: 'Are you sure?',
                text: `${action === 'start' ? 'Start' : 'Stop'} ${ids.length} selected profile(s)?`,
                icon: 'warning',
                background: '#111827',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: action === 'start' ? '#10b981' : '#ef4444',
                cancelButtonColor: '#374151',
                confirmButtonText: action === 'start' ? 'Yes, start!' : 'Yes, stop!'
            }).then(async result => {
                if (!result.isConfirmed) return;
                state.bulkRunning = true;
                let ok = 0;
                let fail = 0;
                for (const id of ids) {
                    state.busy.add(id);
                    render();
                    try {
                        await post(`api/chromium/profiles/${id}/${action}`);
                        ok++;
                    } catch (error) {
                        fail++;
                    }
                    state.busy.delete(id);
                    await new Promise(resolve => setTimeout(resolve, 400)); // jeda agar tidak membanjiri proses
                }
                state.bulkRunning = false;
                state.selected.clear();
                showAlert(`${ok} succeeded${fail ? `, ${fail} failed` : ''}`, fail ? 'error' : 'success');
                await loadProfiles(true).catch(() => {});
            });
        }

        function deleteProfile(id) {
            const profile = state.profiles.find(item => Number(item.id) === Number(id));
            Swal.fire({
                title: 'Hapus profil?',
                text: `${profile ? profile.profile_name : `Profil ${id}`} beserta semua cookies dan data lokalnya akan dihapus.`,
                icon: 'warning',
                background: '#111827',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#374151',
                confirmButtonText: 'Yes, delete!'
            }).then(async result => {
                if (!result.isConfirmed) return;
                try {
                    const response = await post(`api/chromium/profiles/${id}/delete`);
                    state.selected.delete(Number(id));
                    showAlert(response.message || 'Profil dihapus.', 'success');
                } catch (error) {
                    showAlert(error.message, 'error');
                }
                await loadProfiles(true).catch(() => {});
            });
        }

        function copyId(id) {
            navigator.clipboard.writeText(String(id));
            showAlert('Profile ID copied to clipboard!', 'success');
        }

        // ---- Selection ----
        document.addEventListener('change', event => {
            if (event.target.id === 'selectAll') {
                const start = (state.page - 1) * PAGE_SIZE;
                visibleProfiles().slice(start, start + PAGE_SIZE).forEach(profile => {
                    event.target.checked ? state.selected.add(Number(profile.id)) : state.selected.delete(Number(profile.id));
                });
                document.querySelectorAll('#profilesList input[type="checkbox"]').forEach(checkbox => checkbox.checked = event.target.checked);
                updateBulkActions();
            } else if (event.target.closest('#profilesList') && event.target.type === 'checkbox') {
                const id = Number(event.target.closest('tr').dataset.id);
                event.target.checked ? state.selected.add(id) : state.selected.delete(id);
                updateBulkActions();
            }
        });

        function clearSelection() {
            state.selected.clear();
            document.querySelectorAll('#profilesList input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);
            updateBulkActions();
        }

        function updateBulkActions() {
            const count = state.selected.size;
            document.getElementById('bulkActions').classList.toggle('hidden', count === 0);
            document.getElementById('selectedCount').textContent = count;

            const start = (state.page - 1) * PAGE_SIZE;
            const pageRows = visibleProfiles().slice(start, start + PAGE_SIZE);
            const selectedOnPage = pageRows.filter(profile => state.selected.has(Number(profile.id))).length;
            const all = document.getElementById('selectAll');
            all.checked = pageRows.length > 0 && selectedOnPage === pageRows.length;
            all.indeterminate = selectedOnPage > 0 && selectedOnPage < pageRows.length;
        }

        // ---- Search & filter ----
        let searchTimer;
        document.getElementById('searchBox').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.search = this.value.trim();
                state.page = 1;
                render();
            }, 200);
        });

        document.getElementById('filterTabs').addEventListener('click', event => {
            const button = event.target.closest('button[data-filter]');
            if (!button) return;
            state.filter = button.dataset.filter;
            state.page = 1;
            render();
        });

        // ---- Profile modal (create / edit) ----
        function openProfileModal(id, focus = 'name') {
            const profile = id === null ? null : state.profiles.find(item => Number(item.id) === Number(id));
            if (id !== null && !profile) return;
            state.editingId = profile ? Number(profile.id) : null;
            document.getElementById('profileModalTitle').textContent = profile ? 'Edit profile' : 'New profile';
            document.getElementById('profileModalSubtitle').textContent = profile ? `ID ${profile.id} • BayzBot ID ${profile.bridge_id}` : 'Profil Chromium baru dengan folder data lokal sendiri.';
            document.getElementById('profileName').value = profile ? profile.profile_name : '';
            fillSelect(document.getElementById('profileProxy'), state.proxies, 'No proxy', profile?.proxy_id ?? 'none');
            fillSelect(document.getElementById('profilePlaylist'), state.playlists, 'No playlist', profile?.playlist_id ?? 'none');
            document.getElementById('profileSaveBtn').textContent = profile ? 'SAVE' : 'CREATE';
            document.getElementById('profileModal').classList.remove('hidden');
            const target = {
                proxy: 'profileProxy',
                playlist: 'profilePlaylist'
            } [focus] || 'profileName';
            setTimeout(() => document.getElementById(target).focus(), 30);
        }

        function closeProfileModal() {
            document.getElementById('profileModal').classList.add('hidden');
            state.editingId = null;
        }

        document.getElementById('profileForm').addEventListener('submit', async event => {
            event.preventDefault();
            const button = document.getElementById('profileSaveBtn');
            const data = {
                profile_name: document.getElementById('profileName').value.trim(),
                proxy_id: document.getElementById('profileProxy').value,
                playlist_id: document.getElementById('profilePlaylist').value
            };
            button.disabled = true;
            try {
                const result = state.editingId === null ?
                    await post('api/chromium/profiles', data) :
                    await post(`api/chromium/profiles/${state.editingId}/update`, data);
                closeProfileModal();
                showAlert(result.message || 'Tersimpan.', 'success');
                await loadProfiles(true);
            } catch (error) {
                showAlert(error.message, 'error');
            } finally {
                button.disabled = false;
            }
        });

        // ---- Executable modal ----
        function openExecutableModal() {
            document.getElementById('executableModal').classList.remove('hidden');
            setTimeout(() => document.getElementById('executablePath').focus(), 30);
        }

        function closeExecutableModal() {
            document.getElementById('executableModal').classList.add('hidden');
        }

        document.getElementById('executableForm').addEventListener('submit', async event => {
            event.preventDefault();
            const button = document.getElementById('executableSaveBtn');
            button.disabled = true;
            try {
                const result = await post('api/chromium/executable', {
                    executable: document.getElementById('executablePath').value.trim()
                });
                closeExecutableModal();
                showAlert(result.message || 'Path disimpan.', 'success');
            } catch (error) {
                showAlert(error.message, 'error');
            } finally {
                button.disabled = false;
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            if (!document.getElementById('profileModal').classList.contains('hidden')) closeProfileModal();
            if (!document.getElementById('executableModal').classList.contains('hidden')) closeExecutableModal();
        });

        // ---- Init ----
        refreshAll();
        setInterval(() => loadProfiles(true).catch(error => console.error(error)), 10000);
    </script>
</body>

</html>
