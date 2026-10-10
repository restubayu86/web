<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browser - Dolphin{anty} Monitoring</title>
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
                            600: '#374151',
                        },
                        brand: {
                            500: '#06b6d4',
                            600: '#0891b2',
                            400: '#22d3ee',
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

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #111827;
        }

        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
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
                    <i class="bi bi-broadcast"></i> Dolphin{anty} Monitoring Suite
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    Browser Profiles
                    <span id="totalBadge" class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-medium">0 Total</span>
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <input type="text" id="searchBox" autocomplete="off" placeholder="Search profiles..." class="px-4 py-2 rounded-lg bg-dark-800 border border-gray-700 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                    <i class="bi bi-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-600"></i>
                </div>
                <button id="refreshBtn" onclick="loadProfiles()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">
                    <i class="bi bi-arrow-clockwise"></i> REFRESH
                </button>
                <a href="<?= base_url('logout') ?>" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl bg-dark-800 border border-gray-700 text-gray-300 text-sm font-medium hover:bg-dark-700 hover:text-rose-400 transition-all" title="Logout">
                    <i class="bi bi-person-circle text-cyan-400"></i>
                    <?php $username = session()->get('username'); ?>
                    <?= esc(is_string($username) ? $username : 'Admin') ?>
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </header>

        <!-- Alert Container -->
        <div id="alertContainer" class="fixed top-6 right-6 z-[100] space-y-3 w-80"></div>

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
                Auto-refresh every 15s
            </div>
        </div>

        <!-- Profiles Table -->
        <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <p class="px-4 py-2 text-[11px] text-gray-500 sm:hidden">Geser tabel ke samping untuk melihat semua kolom.</p>
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="w-full min-w-[1160px]">
                    <thead class="border-b border-gray-800 bg-dark-900/50">
                        <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <th class="w-14 px-4 py-4 text-left"><input type="checkbox" id="selectAll" class="w-5 h-5 rounded border-2 border-gray-600 text-cyan-500 bg-dark-800 cursor-pointer focus:ring-2 focus:ring-cyan-500 focus:ring-offset-0 hover:border-gray-500 transition-colors"></th>
                            <th class="w-56 px-4 py-4 text-left">Name</th>
                            <th class="w-40 px-4 py-4 text-left">Instance</th>
                            <th class="w-56 px-4 py-4 text-left">Proxy</th>
                            <th class="w-48 px-4 py-4 text-left">Playlist</th>
                            <th class="w-28 px-4 py-4 text-left">Timer</th>
                            <th class="w-28 px-4 py-4 text-left">Worktime</th>
                            <th class="w-40 px-4 py-4 text-left">Total Stream<br><span class="normal-case tracking-normal font-normal">Total &amp; Today</span></th>
                        </tr>
                    </thead>
                    <tbody id="profilesList" class="divide-y divide-gray-800">
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
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

        <div id="proxyModal" class="hidden fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="proxyModalTitle" onclick="if (event.target === this) closeProxyModal()">
            <div id="proxyModalCard" class="w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-700 bg-dark-800 text-gray-100 shadow-2xl">
                <div id="proxyModalHeader" class="flex items-start justify-between border-b border-gray-700 px-5 py-5 sm:px-8">
                    <div>
                        <h2 id="proxyModalTitle" class="text-xl font-bold">Edit proxy</h2>
                        <p id="proxyModalProfileName" class="mt-1 text-xs text-gray-400"></p>
                    </div>
                    <button id="proxyModalClose" type="button" onclick="closeProxyModal()" aria-label="Close" class="text-gray-400 hover:text-white transition-colors"><i class="bi bi-x-lg text-lg"></i></button>
                </div>
                <div id="proxyModalBody" class="max-h-[70vh] space-y-6 overflow-y-auto p-5 sm:p-6">
                    <div id="proxyModalTabs" class="flex flex-wrap gap-2 border-b border-gray-700">
                        <button id="noProxyTab" type="button" onclick="setProxyModalTab('none')" class="border-b-2 border-transparent px-3 py-3 text-sm font-medium text-gray-400 hover:text-gray-200">No proxy</button>
                        <button id="newProxyTab" type="button" onclick="setProxyModalTab('new')" class="border-b-2 border-transparent px-3 py-3 text-sm font-medium text-gray-400 hover:text-gray-200"><i class="bi bi-plus-square mr-1"></i> New proxy</button>
                        <button id="savedProxyTab" type="button" onclick="setProxyModalTab('saved')" class="border-b-2 border-cyan-400 px-3 py-3 text-sm font-medium text-cyan-400">Saved proxy</button>
                        <a id="proxyModalManageLink" href="<?= esc(base_url('proxies'), 'attr') ?>" class="px-3 py-3 text-sm font-medium text-gray-400 hover:text-gray-200">Manage proxies</a>
                    </div>

                    <div id="proxyModalNonePanel" class="hidden rounded-xl border border-gray-700 bg-dark-900 p-4 text-sm text-gray-300">
                        This profile will use a direct connection without a proxy.
                    </div>

                    <div id="proxyModalNewPanel" class="hidden space-y-5">
                        <div class="flex gap-2">
                            <input type="text" id="newProxyQuickInput" autocomplete="off" placeholder="Paste proxy (e.g. http://user:pass@host:port)" class="min-w-0 flex-1 rounded-lg border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 placeholder-gray-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            <button type="button" onclick="parseNewProxyInput()" class="rounded-lg border border-gray-700 bg-dark-700 px-4 py-3 text-cyan-400 hover:bg-dark-600" title="Parse proxy"><i class="bi bi-lightning-charge"></i></button>
                        </div>
                        <div class="flex flex-wrap gap-2" id="newProxyProtocols">
                            <button type="button" onclick="setNewProxyProtocol('http')" data-proto="http" class="rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-4 py-2 text-sm font-medium text-cyan-400">HTTP</button>
                            <button type="button" onclick="setNewProxyProtocol('socks4')" data-proto="socks4" class="rounded-lg bg-dark-700 px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-300">SOCKS4</button>
                            <button type="button" onclick="setNewProxyProtocol('socks5')" data-proto="socks5" class="rounded-lg bg-dark-700 px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-300">SOCKS5</button>
                            <button type="button" onclick="setNewProxyProtocol('ssh')" data-proto="ssh" class="rounded-lg bg-dark-700 px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-300">SSH</button>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <input type="text" id="newProxyHost" autocomplete="off" placeholder="Host" class="min-w-0 rounded-lg border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 placeholder-gray-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            <input type="number" id="newProxyPort" autocomplete="off" min="1" max="65535" placeholder="Port" class="min-w-0 rounded-lg border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 placeholder-gray-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            <input type="text" id="newProxyLogin" autocomplete="off" placeholder="Login (optional)" class="min-w-0 rounded-lg border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 placeholder-gray-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            <input type="password" id="newProxyPassword" autocomplete="new-password" placeholder="Password (optional)" class="min-w-0 rounded-lg border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 placeholder-gray-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                        </div>
                        <input type="text" id="newProxyName" autocomplete="off" placeholder="Proxy name (optional)" class="w-full rounded-lg border border-gray-700 bg-dark-900 px-4 py-3 text-sm text-gray-200 placeholder-gray-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div id="newProxyTestStatus" class="hidden rounded-lg border px-3 py-2 text-xs"></div>
                            <button type="button" onclick="testNewProxyConnection()" class="inline-flex items-center gap-2 rounded-lg border border-gray-700 bg-dark-700 px-4 py-2.5 text-sm font-medium text-cyan-400 hover:bg-dark-600"><i class="bi bi-wifi"></i> Test connection</button>
                        </div>
                    </div>

                    <div id="proxyModalSavedPanel">
                        <label id="proxyModalSelectLabel" for="proxyModalSelect" class="block text-sm text-gray-400">Change proxy</label>
                        <select id="proxyModalSelect" class="mt-2 w-full rounded-lg border border-gray-700 bg-dark-900 px-3 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            <option value="none">No proxy</option>
                            <option value="">Select a saved Dolphin{anty} proxy</option>
                        </select>

                        <div id="proxyModalSelectedLabel" class="mt-5 text-sm text-gray-400">Selected proxy</div>
                        <div id="proxyModalSelectedCard" class="mt-2 rounded-lg border border-gray-800 bg-dark-900 p-4">
                            <div id="proxyModalAddress" class="break-all font-mono text-sm text-gray-200">No proxy selected</div>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                <div id="proxyModalCheck" class="flex items-center gap-2 text-xs text-gray-500">
                                    <i class="bi bi-info-circle"></i><span>Select a proxy to view its details.</span>
                                </div>
                                <button id="proxyModalTest" type="button" onclick="testSelectedProxy()" class="hidden items-center gap-1 text-xs font-semibold uppercase tracking-wider text-cyan-400 hover:text-cyan-300 disabled:opacity-50">
                                    <i class="bi bi-wifi"></i> Test connection
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="proxyModalFooter" class="flex justify-end gap-3 border-t border-gray-700 bg-dark-900/50 px-5 py-4 sm:px-6">
                    <button id="proxyModalCancel" type="button" onclick="closeProxyModal()" class="rounded-lg bg-dark-700 px-5 py-3 text-sm font-medium text-gray-300 hover:bg-dark-600">CANCEL</button>
                    <button id="proxyModalApply" type="button" onclick="applyModalProxy()" disabled class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-cyan-500/20 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40">CHANGE</button>
                    <button id="proxyModalCreate" type="button" onclick="createProxyFromModal()" class="hidden rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-cyan-500/20 hover:opacity-95 disabled:cursor-wait disabled:opacity-50">ADD PROXY</button>
                </div>
            </div>
        </div>
        <div id="playlistModal" class="hidden fixed inset-0 z-[85] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="playlistModalTitle" onclick="if (event.target === this) closePlaylistModal()">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-gray-700 bg-dark-800 text-gray-100 shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-700 px-5 py-5 sm:px-8">
                    <div>
                        <h2 id="playlistModalTitle" class="text-xl font-bold text-white">Change playlist</h2>
                        <p id="playlistModalProfileName" class="mt-1 text-xs text-gray-400"></p>
                    </div>
                    <button type="button" onclick="closePlaylistModal()" aria-label="Close" class="text-gray-400 transition-colors hover:text-white"><i class="bi bi-x-lg text-lg"></i></button>
                </div>
                <div class="space-y-3 p-5 sm:p-6">
                    <label for="playlistModalSelect" class="block text-sm text-gray-400">Select playlist</label>
                    <select id="playlistModalSelect" class="w-full rounded-lg border border-gray-700 bg-dark-900 px-3 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                        <option value="none">No playlist</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-700 bg-dark-900/50 px-5 py-4 sm:px-6">
                    <button type="button" onclick="closePlaylistModal()" class="rounded-lg bg-dark-700 px-5 py-3 text-sm font-medium text-gray-300 hover:bg-dark-600">CANCEL</button>
                    <button id="playlistModalApply" type="button" onclick="applyProfilePlaylist()" disabled class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-cyan-500/20 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40">CHANGE</button>
                </div>
            </div>
        </div>
        <div id="showPlaylistModal" class="hidden fixed inset-0 z-[90] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="showPlaylistTitle" onclick="if (event.target === this) closeShowPlaylistModal()">
            <div class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-700 bg-dark-800 text-gray-100 shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-700 bg-dark-900/50 px-5 py-5 sm:px-8">
                    <div>
                        <h2 id="showPlaylistTitle" class="text-xl font-bold text-white">Playlist</h2>
                        <p id="showPlaylistProfileName" class="mt-1 text-xs text-gray-400"></p>
                    </div>
                    <button type="button" onclick="closeShowPlaylistModal()" aria-label="Close" class="text-gray-400 transition-colors hover:text-white"><i class="bi bi-x-lg text-lg"></i></button>
                </div>
                <div id="showPlaylistContent" class="min-h-32 flex-1 overflow-y-auto p-5 sm:p-6">
                    <div class="flex items-center justify-center gap-2 py-8 text-sm text-gray-400"><i class="bi bi-arrow-repeat animate-spin"></i> Loading playlist...</div>
                </div>
                <div class="flex justify-end border-t border-gray-700 bg-dark-900/50 px-5 py-4 sm:px-6">
                    <button type="button" onclick="closeShowPlaylistModal()" class="rounded-lg bg-dark-700 px-5 py-3 text-sm font-medium text-gray-300 hover:bg-dark-600">CLOSE</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        const baseUrl = '<?= esc(base_url(), 'js') ?>';
        const REFRESH_MS = 15000;
        const PROFILE_RUNTIME_STORAGE_KEY = 'dolphin-profile-runtime';

        const state = {
            profiles: [],
            filter: 'all',
            search: '',
            page: 1,
            lastPage: 1,
            total: null,
            selected: new Set(),
            busy: new Set(),
            proxyBusy: new Set(),
            runtimeFromLocalApi: new Map(),
            dolphinProxies: [],
            playlists: [],
            playlistsLoaded: false,
            proxyModalProfileId: null,
            proxyCheck: null,
            playlistModalProfileId: null,
            showPlaylistRequest: 0,
            playlistBusy: new Set(),
            proxyModalTab: 'saved',
            newProxyType: 'http',
            newProxyBusy: false,
            loading: false,
            bulkRunning: false,
        };

        function loadRuntimeState() {
            try {
                const saved = JSON.parse(localStorage.getItem(PROFILE_RUNTIME_STORAGE_KEY) || '{}');
                if (!saved || typeof saved !== 'object' || Array.isArray(saved)) return;
                Object.entries(saved).forEach(([id, running]) => {
                    const profileId = Number(id);
                    if (Number.isSafeInteger(profileId) && profileId > 0 && typeof running === 'boolean') {
                        state.runtimeFromLocalApi.set(profileId, running);
                    }
                });
            } catch (error) {
                console.warn('Could not restore browser profile runtime state.', error);
            }
        }

        function saveRuntimeState() {
            try {
                localStorage.setItem(
                    PROFILE_RUNTIME_STORAGE_KEY,
                    JSON.stringify(Object.fromEntries(state.runtimeFromLocalApi))
                );
            } catch (error) {
                console.warn('Could not persist browser profile runtime state.', error);
            }
        }

        loadRuntimeState();

        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [c]));
        }

        // Satu-satunya tempat yang tahu bentuk data profil dari Dolphin{anty}.
        function profileRunningState(profile) {
            const values = [
                profile.running,
                profile.isRunning,
                profile.is_running,
                profile.browserRunning,
                profile.browser_running,
            ];

            for (const value of values) {
                if (typeof value === 'boolean') return value;
                if (typeof value === 'number' && (value === 0 || value === 1)) return value === 1;
                if (typeof value !== 'string') continue;

                const status = value.trim().toLowerCase();
                if (['1', 'true', 'running', 'active', 'started', 'open', 'launched'].includes(status)) return true;
                if (['0', 'false', 'stopped', 'inactive', 'closed', 'stop'].includes(status)) return false;
            }

            return null;
        }

        function normalizeProfile(p) {
            const apiRunning = profileRunningState(p);
            const profileId = Number(p.id);
            if (apiRunning !== null && apiRunning === state.runtimeFromLocalApi.get(profileId)) {
                state.runtimeFromLocalApi.delete(profileId);
                saveRuntimeState();
            }
            const running = state.runtimeFromLocalApi.has(profileId) ?
                state.runtimeFromLocalApi.get(profileId) :
                apiRunning;
            let proxy = null;
            if (p.proxy && typeof p.proxy === 'object') {
                const hostPort = p.proxy.host ? p.proxy.host + (p.proxy.port ? ':' + p.proxy.port : '') : '';
                const lastCheck = p.proxy.lastCheck && typeof p.proxy.lastCheck === 'object' ? p.proxy.lastCheck : {};
                proxy = {
                    label: hostPort ? `${p.proxy.type || 'http'}://${hostPort}` : (p.proxy.name || '-'),
                    type: (p.proxy.type || '').toString().toUpperCase(),
                    ip: lastCheck.ip || p.proxy.ip || '',
                    country: lastCheck.country || p.proxy.country || '',
                    checked: lastCheck.status === true || lastCheck.status === 'working',
                };
            }
            return {
                id: profileId,
                name: p.name || ('Profile ' + p.id),
                running,
                playlistId: Number(p.playlistId ?? p.playlist_id) || null,
                playlistName: p.playlistName || p.playlist_name || '',
                proxy,
                proxyId: Number(p.proxyId ?? p.proxy_id ?? p.proxy?.id) || null,
                platform: (p.platform || '').toString().toLowerCase(),
                browserType: (p.browserType || p.browser_type || '').toString().toLowerCase(),
                site: p.mainWebsite || '',
                timer: p.timer ?? p.elapsedTime ?? p.elapsed_time ?? null,
                worktime: p.worktime ?? p.workTime ?? p.work_time ?? null,
                totalStream: p.totalStream ?? p.total_stream ?? p.totalStreams ?? null,
                totalStreamToday: p.totalStreamToday ?? p.total_stream_today ?? p.streamsToday ?? null,
            };
        }

        function displayMetric(value) {
            return value === null || value === undefined || value === '' ? '-' : esc(value);
        }

        function formatDuration(seconds) {
            const totalSeconds = Number(seconds);
            if (!Number.isFinite(totalSeconds) || totalSeconds <= 0) return '-';

            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const remainingSeconds = Math.floor(totalSeconds % 60);
            const paddedSeconds = String(remainingSeconds).padStart(2, '0');
            return hours > 0 ?
                `${hours}:${String(minutes).padStart(2, '0')}:${paddedSeconds}` :
                `${minutes}:${paddedSeconds}`;
        }

        let countryCodeMap = null;

        function getCountryCode(country) {
            const raw = String(country || '').trim();
            if (!raw || raw.toLowerCase() === 'unknown') return '';
            if (/^[A-Za-z]{2}$/.test(raw)) return raw.toLowerCase();

            if (!countryCodeMap) {
                countryCodeMap = {
                    'united states': 'us',
                    'usa': 'us',
                    'united kingdom': 'gb',
                    'uk': 'gb',
                    'hong kong': 'hk',
                    'macau': 'mo',
                    'south korea': 'kr',
                    'north korea': 'kp',
                    'russia': 'ru',
                    'vietnam': 'vn',
                    'turkey': 'tr',
                    'czech republic': 'cz',
                    'taiwan': 'tw',
                    'moldova': 'md',
                    'laos': 'la',
                    'iran': 'ir',
                    'syria': 'sy',
                    'bolivia': 'bo',
                    'venezuela': 've',
                    'tanzania': 'tz',
                    'brunei': 'bn',
                    'palestine': 'ps',
                    'ivory coast': 'ci',
                    'myanmar': 'mm',
                    'kosovo': 'xk',
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

        function proxyOptionLabel(proxy, country = '') {
            const proxyCountry = country || proxy.lastCheck?.country || proxy.country || '';
            const countryCode = getCountryCode(proxyCountry).toUpperCase();
            const flag = countryCode ?
                String.fromCodePoint(...Array.from(countryCode, letter => letter.charCodeAt(0) + 127397)) :
                '🌐';
            const protocol = String(proxy.type || 'http').toLowerCase();
            const host = String(proxy.host || '');
            const port = proxy.port ? `:${proxy.port}` : '';
            const address = host ? `${protocol}://${host}${port}` : String(proxy.label || '-');
            return `${flag} ${countryCode || '--'} — ${address}`;
        }

        async function loadProfiles(silent = false) {
            if (state.loading) return false;
            state.loading = true;
            const btn = document.getElementById('refreshBtn');
            if (!silent) btn.querySelector('i').classList.add('animate-spin');

            const params = new URLSearchParams({
                page: state.page,
                limit: 50
            });
            if (state.search) params.set('query', state.search);

            try {
                const res = await fetch(`${baseUrl}api/profiles?${params}`);
                const data = await res.json().catch(() => null);

                if (!res.ok || !data || !Array.isArray(data.data)) {
                    renderError((data && data.message) || 'Unexpected response from Dolphin{anty} API', res.status === 400);
                    return false;
                }

                state.profiles = data.data.map(normalizeProfile);
                const meta = data.meta || {};
                state.page = meta.current_page ?? meta.page ?? state.page;
                state.lastPage = meta.last_page ?? meta.lastPage ?? 1;
                if (state.lastPage <= 1 && data.links && data.links.next) state.lastPage = state.page + 1;
                state.total = meta.total ?? null;

                // buang pilihan yang sudah tidak ada di halaman ini
                const ids = new Set(state.profiles.map(p => p.id));
                state.selected.forEach(id => {
                    if (!ids.has(id)) state.selected.delete(id);
                });
                render();
                return true;
            } catch (e) {
                renderError('Failed to connect to the server.', false);
                return false;
            } finally {
                state.loading = false;
                btn.querySelector('i').classList.remove('animate-spin');
            }
        }

        function renderError(message, needsToken) {
            document.getElementById('profilesList').innerHTML = `
                <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">
                    <div class="flex flex-col items-center justify-center">
                        <i class="bi bi-exclamation-triangle text-4xl mb-3 text-rose-400 opacity-60"></i>
                        <p class="text-sm text-rose-400">${esc(message)}</p>
                        ${needsToken ? `<a href="${esc(baseUrl)}settings" class="mt-3 text-sm text-cyan-400 hover:text-cyan-300">Open Settings <i class="bi bi-arrow-right"></i></a>` : ''}
                    </div>
                </td></tr>`;
            document.getElementById('pager').classList.add('hidden');
            updateBulkActions();
        }

        function visibleProfiles() {
            return state.profiles.filter(p =>
                state.filter === 'all' ||
                (state.filter === 'running' && p.running === true) ||
                (state.filter === 'stopped' && p.running === false)
            );
        }

        function render() {
            const running = state.profiles.filter(p => p.running === true).length;
            const stopped = state.profiles.filter(p => p.running === false).length;
            const total = state.total ?? state.profiles.length;
            document.getElementById('statTotal').textContent = total;
            document.getElementById('statRunning').textContent = running;
            document.getElementById('statStopped').textContent = stopped;
            document.getElementById('totalBadge').textContent = `${total} Total`;

            document.querySelectorAll('#filterTabs button').forEach(b => {
                const active = b.dataset.filter === state.filter;
                b.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors ' +
                    (active ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' : 'text-gray-400 hover:text-gray-200');
            });

            const rows = visibleProfiles();
            const list = document.getElementById('profilesList');
            if (rows.length === 0) {
                list.innerHTML = `
                    <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="bi bi-browser-chrome text-4xl mb-3 opacity-20"></i>
                            <p class="text-sm">${state.search || state.filter !== 'all' ? 'No profiles match your filter.' : 'No browser profiles found.'}</p>
                        </div>
                    </td></tr>`;
            } else {
                list.innerHTML = rows.map(rowHtml).join('');
            }
            renderPager();
            updateBulkActions();
        }

        function rowHtml(p) {
            const busy = state.busy.has(p.id);
            const proxyBusy = state.proxyBusy.has(p.id);
            const countryCode = getCountryCode(p.proxy?.country);
            const action = busy ?
                `<button disabled class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-gray-600 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-wait"><i class="bi bi-arrow-repeat animate-spin inline-block"></i></button>` :
                (p.running === null ?
                    (p.playlistId ?
                        `<button onclick="runAction(${p.id}, 'start')" class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-emerald-500 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-emerald-400 hover:bg-emerald-500/10 transition-colors" title="Start profile and play assigned playlist"><i class="bi bi-play-fill"></i> Start</button>` :
                        `<button disabled class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-gray-700 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-not-allowed" title="Assign a playlist once before starting"><i class="bi bi-play-fill"></i> Start</button>`) :
                    (p.running ?
                        `<button onclick="runAction(${p.id}, 'stop')" class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-rose-500 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-rose-400 hover:bg-rose-500/10 transition-colors" title="Stop profile"><i class="bi bi-stop-fill"></i> Stop</button>` :
                        (p.playlistId ?
                            `<button onclick="runAction(${p.id}, 'start')" class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-emerald-500 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-emerald-400 hover:bg-emerald-500/10 transition-colors" title="Start profile and play assigned playlist"><i class="bi bi-play-fill"></i> Start</button>` :
                            `<button disabled class="inline-flex min-w-24 items-center justify-center gap-2 rounded-md border border-gray-700 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-not-allowed" title="Assign a playlist once before starting"><i class="bi bi-play-fill"></i> Start</button>`)));

            return `
            <tr class="hover:bg-dark-700/50 transition-colors" data-id="${p.id}">
                <td class="px-4 py-4"><input type="checkbox" ${state.selected.has(p.id) ? 'checked' : ''} class="w-5 h-5 rounded border-2 border-gray-600 text-cyan-500 bg-dark-800 cursor-pointer focus:ring-2 focus:ring-cyan-500 focus:ring-offset-0 hover:border-gray-500 transition-colors"></td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-2">
                        ${p.platform.includes('win') ? '<i class="bi bi-windows text-blue-500 text-lg" title="Windows"></i>' : p.platform.includes('mac') ? '<i class="bi bi-apple text-gray-400 text-lg" title="macOS"></i>' : p.platform ? `<i class="bi bi-laptop text-blue-400 text-lg" title="${esc(p.platform)}"></i>` : ''}
                        <i class="bi ${p.browserType.includes('chrome') || p.browserType.includes('anty') ? 'bi-globe2' : 'bi-browser-chrome'} text-blue-500 text-lg" title="${esc(p.browserType || 'Browser')}"></i>
                        <span class="text-cyan-400 font-medium">${esc(p.name)}</span>
                        <button onclick="copyId(${p.id})" class="text-gray-400 hover:text-gray-200 transition-colors" title="Copy profile ID"><i class="bi bi-copy"></i></button>
                    </div>
                    <div class="text-xs text-gray-400 mt-1 flex items-center gap-1.5">
                        <span class="font-mono">ID ${p.id}</span>
                        ${p.site ? `<span class="text-gray-500">•</span><span class="capitalize">${esc(p.site)}</span>` : ''}
                    </div>
                </td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-3">
                        ${action}
                    </div>
                </td>
                <td class="px-4 py-4 text-sm">
                    <div class="flex min-w-64 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="break-all font-mono text-gray-300">${p.proxy ? esc(p.proxy.label) : (p.proxyId ? `Proxy #${p.proxyId}` : '<span class="text-gray-500">-</span>')}</div>
                            ${p.proxy && (p.proxy.ip || p.proxy.country) ? `<div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500"><i class="bi bi-arrow-repeat text-emerald-400"></i>${countryCode ? `<span class="fi fi-${esc(countryCode)} rounded-sm"></span>` : ''}${p.proxy.ip ? `<span>${esc(p.proxy.ip)}</span>` : ''}${p.proxy.country ? `<span>${esc(p.proxy.country)}</span>` : ''}</div>` : ''}
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <button onclick="testProfileProxy(${p.id})" ${!p.proxyId || proxyBusy ? 'disabled' : ''} class="inline-flex items-center gap-1.5 rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-cyan-400 hover:border-cyan-500 hover:text-cyan-300 disabled:cursor-not-allowed disabled:opacity-40" title="Test proxy connection"><i class="bi ${proxyBusy ? 'bi-arrow-repeat animate-spin' : 'bi-wifi'}"></i></button>
                            <button onclick="openProxyModal(${p.id})" class="shrink-0 rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-gray-300 hover:border-cyan-500 hover:text-cyan-300" title="Edit profile proxy" aria-label="Edit profile proxy"><i class="bi bi-pencil"></i></button>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-4 text-sm">
                    <div class="flex min-w-40 items-center justify-between gap-2">
                        <span class="truncate text-gray-300" title="${esc(p.playlistName || 'No playlist')}">${esc(p.playlistName || '-')}</span>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <button onclick="showProfilePlaylist(${p.id})" ${!p.playlistId ? 'disabled' : ''} class="rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-cyan-400 hover:border-cyan-500 hover:text-cyan-300 disabled:cursor-not-allowed disabled:opacity-40" title="Show playlist" aria-label="Show playlist"><i class="bi bi-eye"></i></button>
                            <button onclick="openPlaylistModal(${p.id})" ${state.playlistBusy.has(p.id) ? 'disabled' : ''} class="rounded-md border border-gray-700 px-2.5 py-1.5 text-xs text-gray-300 hover:border-cyan-500 hover:text-cyan-300 disabled:cursor-wait disabled:opacity-50" title="Change profile playlist" aria-label="Change profile playlist"><i class="bi ${state.playlistBusy.has(p.id) ? 'bi-arrow-repeat animate-spin' : 'bi-pencil'}"></i></button>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-4 text-sm text-gray-300">${displayMetric(p.timer)}</td>
                <td class="px-4 py-4 text-sm text-gray-300">${displayMetric(p.worktime)}</td>
                <td class="px-4 py-4 text-sm">
                    <div class="text-gray-300"><span class="text-gray-500">Total:</span> ${displayMetric(p.totalStream)}</div>
                    <div class="text-gray-400 mt-1"><span class="text-gray-500">Today:</span> ${displayMetric(p.totalStreamToday)}</div>
                </td>
            </tr>`;
        }

        function renderPager() {
            const pager = document.getElementById('pager');
            if (state.lastPage <= 1) {
                pager.classList.add('hidden');
                return;
            }
            pager.classList.remove('hidden');
            document.getElementById('pagerInfo').textContent = `Page ${state.page} of ${state.lastPage}`;
            document.getElementById('prevPage').disabled = state.page <= 1;
            document.getElementById('nextPage').disabled = state.page >= state.lastPage;
        }

        function goPage(delta) {
            state.page = Math.max(1, state.page + delta);
            state.selected.clear();
            loadProfiles();
        }

        // ---- Start / Stop ----
        async function loadDolphinProxies() {
            try {
                const res = await fetch(`${baseUrl}api/proxy/dolphin-options`);
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success || !Array.isArray(data.data)) {
                    throw new Error(data.message || `Failed to load Dolphin proxies (HTTP ${res.status})`);
                }
                state.dolphinProxies = data.data;
                render();
            } catch (error) {
                showAlert(error.message || 'Failed to load Dolphin proxies', 'error');
            }
        }

        async function loadPlaylists() {
            try {
                const response = await fetch(`${baseUrl}api/playlist/all`);
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success || !Array.isArray(data.data)) {
                    throw new Error(data.message || `Failed to load playlists (HTTP ${response.status})`);
                }
                state.playlists = data.data;
                state.playlistsLoaded = true;
                return true;
            } catch (error) {
                showAlert(error.message || 'Failed to load playlists.', 'error');
                return false;
            }
        }

        async function showProfilePlaylist(profileId) {
            const profile = state.profiles.find(item => item.id === profileId);
            if (!profile || !profile.playlistId) return;

            const requestId = ++state.showPlaylistRequest;
            const playlist = state.playlists.find(item => Number(item.id) === profile.playlistId);
            document.getElementById('showPlaylistTitle').textContent = playlist?.playlist_name || profile.playlistName || 'Playlist';
            document.getElementById('showPlaylistProfileName').textContent = profile.name;
            const content = document.getElementById('showPlaylistContent');
            content.innerHTML = '<div class="flex items-center justify-center gap-2 py-8 text-sm text-gray-400"><i class="bi bi-arrow-repeat animate-spin"></i> Loading playlist...</div>';
            document.getElementById('showPlaylistModal').classList.remove('hidden');

            try {
                const response = await fetch(`${baseUrl}api/playlist/${encodeURIComponent(profile.playlistId)}`);
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success || !data.data || !Array.isArray(data.data.songs)) {
                    throw new Error(data.message || `Failed to load playlist (HTTP ${response.status})`);
                }
                if (requestId !== state.showPlaylistRequest) return;

                const songs = data.data.songs;
                document.getElementById('showPlaylistTitle').textContent = `${data.data.playlist_name || 'Playlist'} · ${songs.length} songs`;
                content.innerHTML = songs.length ? `
                    <div class="overflow-x-auto rounded-xl border border-gray-700">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="bg-dark-900/80 text-xs uppercase tracking-wider text-gray-400">
                                <tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Title</th><th class="px-4 py-3">Artist</th><th class="px-4 py-3">Duration</th><th class="px-4 py-3 text-right">Plays</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                ${songs.map((song, index) => `
                                    <tr class="hover:bg-dark-700/50">
                                        <td class="px-4 py-3 text-gray-500">${index + 1}</td>
                                        <td class="max-w-sm truncate px-4 py-3 font-medium text-gray-200" title="${esc(song.song_title || '')}">${esc(song.song_title || 'Untitled')}</td>
                                        <td class="max-w-xs truncate px-4 py-3 text-gray-400">${esc(song.artist_name || '-')}</td>
                                        <td id="songDuration-${Number(song.id)}" class="px-4 py-3 text-gray-400">${formatDuration(song.duration) || '-'}</td>
                                        <td class="px-4 py-3 text-right text-gray-400">${displayMetric(song.play_count ?? 0)}</td>
                                    </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>` :
                    '<div class="rounded-xl border border-dashed border-gray-700 px-4 py-10 text-center text-sm text-gray-500">This playlist has no songs.</div>';
                if (requestId === state.showPlaylistRequest) {
                    refreshPlaylistDurations(songs, requestId);
                }
            } catch (error) {
                if (requestId !== state.showPlaylistRequest) return;
                content.innerHTML = `<div class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">${esc(error.message || 'Failed to load playlist.')}</div>`;
            }
        }

        async function refreshPlaylistDurations(songs, requestId) {
            const songsWithoutDuration = songs.filter(song => Number(song.duration) <= 0 && Number(song.id) > 0);
            let nextIndex = 0;

            const worker = async () => {
                while (nextIndex < songsWithoutDuration.length && requestId === state.showPlaylistRequest) {
                    const song = songsWithoutDuration[nextIndex++];
                    try {
                        const response = await fetch(`${baseUrl}api/playlist/song/duration/${encodeURIComponent(song.id)}`, {
                            method: 'POST',
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || `Failed to refresh duration (HTTP ${response.status})`);
                        }
                        if (requestId !== state.showPlaylistRequest || data.data?.duration === null || data.data?.duration === undefined) continue;

                        const durationCell = document.getElementById(`songDuration-${Number(song.id)}`);
                        if (durationCell) durationCell.textContent = formatDuration(data.data.duration);
                    } catch (error) {
                        console.error(`Failed to refresh duration for playlist song ${song.id}:`, error);
                    }
                }
            };

            await Promise.all(Array.from({
                    length: Math.min(4, songsWithoutDuration.length)
                },
                () => worker()
            ));
        }

        function closeShowPlaylistModal() {
            state.showPlaylistRequest++;
            document.getElementById('showPlaylistModal').classList.add('hidden');
        }

        async function openPlaylistModal(profileId) {
            const profile = state.profiles.find(item => item.id === profileId);
            if (!profile) return;
            if (!state.playlistsLoaded && !await loadPlaylists()) return;

            state.playlistModalProfileId = profileId;
            document.getElementById('playlistModalProfileName').textContent = profile.name;
            const select = document.getElementById('playlistModalSelect');
            select.replaceChildren(new Option('No playlist', 'none'));
            state.playlists.forEach(playlist => {
                select.add(new Option(playlist.playlist_name || `Playlist #${playlist.id}`, String(playlist.id)));
            });
            select.value = profile.playlistId ? String(profile.playlistId) : 'none';
            document.getElementById('playlistModal').classList.remove('hidden');
            updatePlaylistModal();
        }

        function closePlaylistModal() {
            document.getElementById('playlistModal').classList.add('hidden');
            state.playlistModalProfileId = null;
        }

        function updatePlaylistModal() {
            const profile = state.profiles.find(item => item.id === state.playlistModalProfileId);
            const select = document.getElementById('playlistModalSelect');
            const busy = state.playlistBusy.has(state.playlistModalProfileId);
            const changed = profile && (select.value === 'none' ?
                Boolean(profile.playlistId) :
                Number(select.value) !== profile.playlistId);
            document.getElementById('playlistModalApply').disabled = !changed || busy;
        }

        async function applyProfilePlaylist() {
            const profileId = state.playlistModalProfileId;
            const playlistId = document.getElementById('playlistModalSelect').value;
            if (!profileId || !playlistId || state.playlistBusy.has(profileId)) return;

            state.playlistBusy.add(profileId);
            updatePlaylistModal();
            render();
            try {
                const response = await fetch(`${baseUrl}api/profiles/${profileId}/playlist`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        playlist_id: playlistId
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || `Failed to update playlist (HTTP ${response.status})`);
                }

                const profile = state.profiles.find(item => item.id === profileId);
                const playlist = state.playlists.find(item => String(item.id) === playlistId);
                if (profile) {
                    profile.playlistId = playlistId === 'none' ? null : Number(playlistId);
                    profile.playlistName = playlist?.playlist_name || '';
                }
                closePlaylistModal();
                render();
                showAlert(data.message || 'Playlist updated for profile.', 'success');
            } catch (error) {
                showAlert(error.message || 'Failed to update profile playlist.', 'error');
            } finally {
                state.playlistBusy.delete(profileId);
                if (state.playlistModalProfileId !== null) updatePlaylistModal();
                render();
            }
        }

        function openProxyModal(profileId) {
            const profile = state.profiles.find(item => item.id === profileId);
            if (!profile) return;

            state.proxyModalProfileId = profileId;
            state.proxyCheck = null;
            state.newProxyType = 'http';
            state.newProxyBusy = false;
            document.getElementById('proxyModalProfileName').textContent = profile.name;
            document.getElementById('newProxyQuickInput').value = '';
            document.getElementById('newProxyHost').value = '';
            document.getElementById('newProxyPort').value = '';
            document.getElementById('newProxyLogin').value = '';
            document.getElementById('newProxyPassword').value = '';
            document.getElementById('newProxyName').value = '';
            document.getElementById('newProxyTestStatus').classList.add('hidden');
            setNewProxyProtocol('http');

            const select = document.getElementById('proxyModalSelect');
            const currentProxyNotListed = profile.proxyId && !state.dolphinProxies.some(proxy => proxy.id === profile.proxyId);
            select.innerHTML = '<option value="none">No proxy</option><option value="">Select a saved Dolphin{anty} proxy</option>' +
                state.dolphinProxies.map(proxy =>
                    `<option value="${esc(proxy.id)}">${esc(proxyOptionLabel(proxy))}</option>`
                ).join('') +
                (currentProxyNotListed ? `<option value="${esc(profile.proxyId)}">${esc(proxyOptionLabel(profile.proxy || {}, profile.proxy?.country))}</option>` : '');
            select.value = profile.proxyId ? String(profile.proxyId) : 'none';
            document.getElementById('proxyModal').classList.remove('hidden');
            setProxyModalTab(profile.proxyId ? 'saved' : 'none');
            updateProxyModal();
        }

        function closeProxyModal() {
            document.getElementById('proxyModal').classList.add('hidden');
            state.proxyModalProfileId = null;
            state.proxyCheck = null;
        }

        function setProxyModalTab(tab) {
            state.proxyModalTab = tab;
            document.getElementById('proxyModalCard').className = 'w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-700 bg-dark-800 text-gray-100 shadow-2xl';
            document.getElementById('proxyModalHeader').className = 'flex items-start justify-between border-b border-gray-700 px-5 py-5 sm:px-8';
            document.getElementById('proxyModalTitle').className = 'text-xl font-bold text-white';
            document.getElementById('proxyModalProfileName').className = 'mt-1 text-xs text-gray-400';
            document.getElementById('proxyModalClose').className = 'text-gray-400 transition-colors hover:text-white';
            document.getElementById('proxyModalTabs').className = 'flex flex-wrap gap-2 rounded-xl border border-gray-700 bg-dark-900 p-1';

            ['noProxyTab', 'newProxyTab', 'savedProxyTab'].forEach(id => {
                const button = document.getElementById(id);
                const active = (id === 'noProxyTab' && tab === 'none') ||
                    (id === 'newProxyTab' && tab === 'new') ||
                    (id === 'savedProxyTab' && tab === 'saved');
                button.className = active ?
                    'rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-2 text-sm font-medium text-cyan-400' :
                    'rounded-lg border border-transparent px-3 py-2 text-sm font-medium text-gray-400 hover:bg-dark-700 hover:text-gray-200';
            });
            document.getElementById('proxyModalManageLink').className = 'rounded-lg px-3 py-2 text-sm font-medium text-gray-400 hover:bg-dark-700 hover:text-gray-200';
            document.getElementById('proxyModalNonePanel').classList.toggle('hidden', tab !== 'none');
            document.getElementById('proxyModalNonePanel').className = `${tab === 'none' ? '' : 'hidden '}rounded-xl border border-gray-700 bg-dark-900 p-4 text-sm text-gray-300`;
            document.getElementById('proxyModalNewPanel').classList.toggle('hidden', tab !== 'new');
            document.getElementById('proxyModalSavedPanel').classList.toggle('hidden', tab !== 'saved');
            document.getElementById('proxyModalBody').className = 'max-h-[70vh] space-y-6 overflow-y-auto bg-dark-800 p-5 sm:p-6';
            document.getElementById('proxyModalSelectLabel').className = 'block text-sm text-gray-400';
            document.getElementById('proxyModalSelect').className = 'mt-2 w-full rounded-lg border border-gray-700 bg-dark-900 px-3 py-3 text-sm text-gray-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500';
            document.getElementById('proxyModalSelectedLabel').className = 'mt-5 text-sm text-gray-400';
            document.getElementById('proxyModalSelectedCard').className = 'mt-2 rounded-lg border border-gray-800 bg-dark-900 p-4';
            document.getElementById('proxyModalAddress').className = 'break-all font-mono text-sm text-gray-200';
            document.getElementById('proxyModalCheck').className = 'flex items-center gap-2 text-xs text-gray-500';
            document.getElementById('proxyModalTest').className = 'hidden items-center gap-1 text-xs font-semibold uppercase tracking-wider text-cyan-400 hover:text-cyan-300 disabled:opacity-50';
            document.getElementById('proxyModalFooter').className = 'flex justify-end gap-3 border-t border-gray-700 bg-dark-900/50 px-5 py-4 sm:px-6';
            document.getElementById('proxyModalCancel').className = 'rounded-lg bg-dark-700 px-5 py-3 text-sm font-medium text-gray-300 hover:bg-dark-600';
            document.getElementById('proxyModalApply').className = 'rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-cyan-500/20 hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40';
            document.getElementById('proxyModalApply').classList.toggle('hidden', tab === 'new');
            document.getElementById('proxyModalCreate').classList.toggle('hidden', tab !== 'new');

            if (tab === 'none') {
                document.getElementById('proxyModalSelect').value = 'none';
            }
            updateProxyModal();
        }

        function updateProxyModal() {
            const select = document.getElementById('proxyModalSelect');
            const proxyId = select.value;
            const address = document.getElementById('proxyModalAddress');
            const check = document.getElementById('proxyModalCheck');
            const testButton = document.getElementById('proxyModalTest');
            const applyButton = document.getElementById('proxyModalApply');

            const profile = state.profiles.find(item => item.id === state.proxyModalProfileId);
            const changed = profile && (proxyId === 'none' ? Boolean(profile.proxyId) : Number(proxyId) !== profile.proxyId);
            applyButton.disabled = !proxyId || !changed || state.proxyBusy.has(state.proxyModalProfileId) || state.proxyModalTab !== 'saved';
            testButton.classList.toggle('hidden', !proxyId || proxyId === 'none');
            testButton.classList.toggle('flex', Boolean(proxyId && proxyId !== 'none'));
            testButton.disabled = state.proxyBusy.has(state.proxyModalProfileId);

            if (state.proxyModalTab !== 'saved') return;

            if (proxyId === 'none') {
                address.textContent = 'No proxy';
                check.innerHTML = '<i class="bi bi-info-circle"></i><span>This profile will use a direct connection.</span>';
                return;
            }

            const proxy = state.dolphinProxies.find(item => String(item.id) === proxyId);
            if (!proxy) {
                const currentProfile = state.profiles.find(item => item.id === state.proxyModalProfileId);
                if (currentProfile && currentProfile.proxyId === Number(proxyId) && currentProfile.proxy) {
                    address.textContent = currentProfile.proxy.label;
                    check.innerHTML = '<i class="bi bi-info-circle"></i><span>Current proxy is not in the saved proxy list.</span>';
                } else {
                    address.textContent = 'Select a saved Dolphin{anty} proxy';
                    check.innerHTML = '<i class="bi bi-info-circle"></i><span>No proxy selected.</span>';
                }
                return;
            }

            address.textContent = `${proxy.type}://${proxy.host}:${proxy.port}`;
            if (state.proxyCheck && String(state.proxyCheck.id) === proxyId) {
                const icon = state.proxyCheck.success ? 'bi-check-circle-fill text-emerald-600' : 'bi-x-circle-fill text-rose-600';
                check.innerHTML = `<i class="bi ${icon}"></i><span>${esc(state.proxyCheck.message)}</span>`;
            } else if (proxy.lastCheck && (proxy.lastCheck.ip || proxy.lastCheck.country || proxy.lastCheck.timezone)) {
                const statusIcon = proxy.lastCheck.status ? 'bi-check-circle-fill text-emerald-600' : 'bi-x-circle-fill text-rose-500';
                const details = [proxy.lastCheck.ip, proxy.lastCheck.country, proxy.lastCheck.timezone]
                    .filter(Boolean)
                    .map(value => `<span>${esc(value)}</span>`)
                    .join('<span class="text-gray-300">·</span>');
                check.innerHTML = `<i class="bi ${statusIcon}"></i>${details}`;
            } else {
                check.innerHTML = '<i class="bi bi-info-circle"></i><span>Test the connection to check this proxy.</span>';
            }
        }

        function setNewProxyProtocol(protocol) {
            state.newProxyType = protocol;
            document.querySelectorAll('#newProxyProtocols button').forEach(button => {
                const active = button.dataset.proto === protocol;
                button.className = active ?
                    'rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-4 py-2 text-sm font-medium text-cyan-400' :
                    'rounded-lg bg-dark-700 px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-300';
            });
            buildNewProxyInput();
        }

        function parseNewProxyInput() {
            const quickInput = document.getElementById('newProxyQuickInput');
            const input = quickInput.value.trim();
            const match = input.match(/^(?:(http|socks4|socks5|ssh):\/\/)?(?:([^:@]+):([^@]+)@)?([^:]+):(\d+)$/i);
            if (!match) {
                showNewProxyTestStatus('Enter proxy in host:port or protocol://user:pass@host:port format.', false);
                return;
            }

            const [, protocol, login, password, host, port] = match;
            if (protocol) setNewProxyProtocol(protocol.toLowerCase());
            document.getElementById('newProxyHost').value = host;
            document.getElementById('newProxyPort').value = port;
            document.getElementById('newProxyLogin').value = login || '';
            document.getElementById('newProxyPassword').value = password || '';
            buildNewProxyInput();
            document.getElementById('newProxyTestStatus').classList.add('hidden');
        }

        function buildNewProxyInput() {
            const host = document.getElementById('newProxyHost').value.trim();
            const port = document.getElementById('newProxyPort').value.trim();
            const login = document.getElementById('newProxyLogin').value;
            const password = document.getElementById('newProxyPassword').value;
            const quickInput = document.getElementById('newProxyQuickInput');

            if (!host || !port) return;

            const credentials = login && password ? `${login}:${password}@` : '';
            quickInput.value = `${state.newProxyType}://${credentials}${host}:${port}`;
        }

        function newProxyFormData() {
            const host = document.getElementById('newProxyHost').value.trim();
            const port = document.getElementById('newProxyPort').value.trim();
            const formData = new FormData();
            const proxyName = document.getElementById('newProxyName').value.trim() || `${host}:${port}`;
            formData.append('proxy_url', `${host}:${port}`);
            formData.append('proxy_host', host);
            formData.append('proxy_port', port);
            formData.append('proxy_login', document.getElementById('newProxyLogin').value);
            formData.append('proxy_password', document.getElementById('newProxyPassword').value);
            formData.append('proxy_type', state.newProxyType);
            formData.append('proxy_name', proxyName);
            formData.append('name', proxyName);
            return formData;
        }

        function showNewProxyTestStatus(message, success, loading = false) {
            const status = document.getElementById('newProxyTestStatus');
            const style = loading ?
                'border-amber-500/30 bg-amber-500/10 text-amber-300' :
                success ?
                'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' :
                'border-rose-500/30 bg-rose-500/10 text-rose-300';
            const icon = loading ? 'bi-arrow-repeat animate-spin' : success ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
            status.className = `rounded-lg border px-3 py-2 text-xs ${style}`;
            status.replaceChildren();
            const iconElement = document.createElement('i');
            iconElement.className = `bi ${icon} mr-1`;
            const messageElement = document.createElement('span');
            messageElement.textContent = message;
            status.append(iconElement, messageElement);
            status.classList.remove('hidden');
        }

        async function testNewProxyConnection() {
            const host = document.getElementById('newProxyHost').value.trim();
            const port = document.getElementById('newProxyPort').value.trim();
            if (!host || !port) {
                showNewProxyTestStatus('Enter host and port first.', false);
                return;
            }

            showNewProxyTestStatus('Testing proxy connection...', false, true);
            try {
                const res = await fetch(`${baseUrl}api/proxy/test-connection`, {
                    method: 'POST',
                    body: newProxyFormData(),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || `Proxy test failed (HTTP ${res.status})`);
                }
                showNewProxyTestStatus([data.message || 'Proxy is working', data.ip, data.country].filter(Boolean).join(' · '), true);
            } catch (error) {
                showNewProxyTestStatus(error.message || 'Proxy connection test failed.', false);
            }
        }

        async function createProxyFromModal() {
            if (state.newProxyBusy) return;
            const host = document.getElementById('newProxyHost').value.trim();
            const port = document.getElementById('newProxyPort').value.trim();
            if (!host || !/^\d+$/.test(port) || Number(port) < 1 || Number(port) > 65535) {
                showNewProxyTestStatus('Enter a valid host and port (1-65535).', false);
                return;
            }

            state.newProxyBusy = true;
            const button = document.getElementById('proxyModalCreate');
            button.disabled = true;
            button.textContent = 'ADDING...';
            try {
                const res = await fetch(`${baseUrl}api/proxy/create`, {
                    method: 'POST',
                    body: newProxyFormData(),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || `Failed to create proxy (HTTP ${res.status})`);
                }

                if (!data.dolphin?.synced) {
                    await loadDolphinProxies();
                    throw new Error(`Proxy was saved locally but not added to Dolphin{anty}: ${data.dolphin?.message || 'Dolphin sync failed.'}`);
                }

                await loadDolphinProxies();
                const dolphinId = Number(data.data?.dolphin_id);
                let createdProxy = state.dolphinProxies.find(proxy => proxy.id === dolphinId);
                if (!createdProxy) {
                    createdProxy = state.dolphinProxies.find(proxy => proxy.host === host && proxy.port === Number(port));
                }
                if (!createdProxy) {
                    throw new Error('Proxy was created, but Dolphin{anty} did not return it in the saved proxy list. Reload the list and try again.');
                }

                const select = document.getElementById('proxyModalSelect');
                if (![...select.options].some(option => option.value === String(createdProxy.id))) {
                    const option = document.createElement('option');
                    option.value = String(createdProxy.id);
                    option.textContent = proxyOptionLabel(createdProxy);
                    select.append(option);
                }
                select.value = String(createdProxy.id);
                state.proxyCheck = null;
                setProxyModalTab('saved');
                showAlert('Proxy added. Click Change to assign it to this profile.', 'success');
            } catch (error) {
                showNewProxyTestStatus(error.message || 'Failed to create proxy.', false);
            } finally {
                state.newProxyBusy = false;
                button.disabled = false;
                button.textContent = 'ADD PROXY';
            }
        }

        async function applyModalProxy() {
            const profileId = state.proxyModalProfileId;
            const proxyId = document.getElementById('proxyModalSelect').value;
            if (!profileId || !proxyId) return;

            state.proxyBusy.add(profileId);
            updateProxyModal();
            try {
                const res = await fetch(`${baseUrl}api/profiles/${profileId}/proxy`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        proxy_id: proxyId
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || `Failed to update proxy (HTTP ${res.status})`);
                }
                showAlert(data.message || `Proxy updated for profile ${profileId}.`, 'success');
                closeProxyModal();
                await loadProfiles(true);
            } catch (error) {
                showAlert(error.message || 'Failed to update profile proxy.', 'error');
            } finally {
                state.proxyBusy.delete(profileId);
                if (state.proxyModalProfileId !== null) updateProxyModal();
                render();
            }
        }

        async function testSelectedProxy() {
            const profileId = state.proxyModalProfileId;
            const proxyId = document.getElementById('proxyModalSelect').value;
            if (!profileId || !proxyId || proxyId === 'none') return;

            state.proxyBusy.add(profileId);
            updateProxyModal();
            document.getElementById('proxyModalCheck').innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i><span>Testing proxy connection...</span>';
            try {
                const res = await fetch(`${baseUrl}api/proxy/dolphin-test/${encodeURIComponent(proxyId)}`, {
                    method: 'POST',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || `Proxy check failed (HTTP ${res.status})`);
                }
                state.proxyCheck = {
                    id: proxyId,
                    success: true,
                    message: [data.message, data.ip, data.country].filter(Boolean).join(' · '),
                };
            } catch (error) {
                state.proxyCheck = {
                    id: proxyId,
                    success: false,
                    message: error.message || 'Proxy connection test failed.',
                };
            } finally {
                state.proxyBusy.delete(profileId);
                updateProxyModal();
            }
        }

        async function testProfileProxy(profileId) {
            const profile = state.profiles.find(item => item.id === profileId);
            if (!profile || !profile.proxyId || state.proxyBusy.has(profileId)) return;

            state.proxyBusy.add(profileId);
            render();
            try {
                const res = await fetch(`${baseUrl}api/proxy/dolphin-test/${encodeURIComponent(profile.proxyId)}`, {
                    method: 'POST',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || `Proxy check failed (HTTP ${res.status})`);
                }
                showAlert([data.message || 'Proxy connection is working', data.ip, data.country].filter(Boolean).join(' · '), 'success');
            } catch (error) {
                showAlert(error.message || 'Proxy connection test failed.', 'error');
            } finally {
                state.proxyBusy.delete(profileId);
                render();
            }
        }

        async function callAction(id, action) {
            try {
                const res = await fetch(`${baseUrl}api/${action}/${id}`, {
                    method: 'POST'
                });
                const data = await res.json().catch(() => ({}));
                const ok = res.ok && data.success !== false;
                return {
                    ok,
                    message: data.message || '',
                    warning: data.warning === true,
                    profileRunning: typeof data.profile_running === 'boolean' ? data.profile_running : null
                };
            } catch (e) {
                return {
                    ok: false,
                    message: 'Network error',
                    profileRunning: null
                };
            }
        }

        async function runAction(id, action) {
            state.busy.add(id);
            render();
            const r = await callAction(id, action);
            const expectedRunning = action === 'start';
            const actionApplied = r.ok || r.profileRunning === expectedRunning;
            if (actionApplied) {
                state.runtimeFromLocalApi.set(id, expectedRunning);
                saveRuntimeState();
                const profile = state.profiles.find(item => item.id === id);
                if (profile) profile.running = expectedRunning;
                render();
                await loadProfiles(true);
                state.busy.delete(id);
                render();
                showAlert(
                    r.message || `Profile ${id} ${expectedRunning ? 'started' : 'stopped'}`,
                    r.warning || !r.ok ? 'info' : 'success'
                );
                return;
            }

            await loadProfiles(true);
            state.busy.delete(id);
            render();
            showAlert(r.message || `Failed to ${action} profile ${id}`, 'error');
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
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                state.bulkRunning = true;
                let ok = 0,
                    fail = 0,
                    warning = 0;
                for (const id of ids) {
                    state.busy.add(id);
                    render();
                    const r = await callAction(id, action);
                    const expectedRunning = action === 'start';
                    const actionApplied = r.ok || r.profileRunning === expectedRunning;
                    if (actionApplied) {
                        ok++;
                        if (r.warning || !r.ok) warning++;
                        state.runtimeFromLocalApi.set(id, expectedRunning);
                        saveRuntimeState();
                        const profile = state.profiles.find(item => item.id === id);
                        if (profile) profile.running = expectedRunning;
                    } else {
                        fail++;
                    }
                    state.busy.delete(id);
                    render();
                    await new Promise(r => setTimeout(r, 400)); // jeda agar tidak membanjiri API
                }
                state.bulkRunning = false;
                state.selected.clear();
                showAlert(
                    `${ok} succeeded${warning ? `, ${warning} with warnings` : ''}${fail ? `, ${fail} failed` : ''}`,
                    fail ? 'error' : warning ? 'info' : 'success'
                );
                await loadProfiles(true);
            });
        }

        // ---- Selection ----
        document.addEventListener('change', function(e) {
            if (e.target.id === 'selectAll') {
                visibleProfiles().forEach(p => e.target.checked ? state.selected.add(p.id) : state.selected.delete(p.id));
                document.querySelectorAll('#profilesList input[type="checkbox"]').forEach(cb => cb.checked = e.target.checked);
                updateBulkActions();
            } else if (e.target.closest('#profilesList') && e.target.type === 'checkbox') {
                const id = Number(e.target.closest('tr').dataset.id);
                e.target.checked ? state.selected.add(id) : state.selected.delete(id);
                updateBulkActions();
            }
        });

        function clearSelection() {
            state.selected.clear();
            document.querySelectorAll('#profilesList input[type="checkbox"]').forEach(cb => cb.checked = false);
            updateBulkActions();
        }

        function updateBulkActions() {
            const bar = document.getElementById('bulkActions');
            const n = state.selected.size;
            bar.classList.toggle('hidden', n === 0);
            document.getElementById('selectedCount').textContent = n;

            const visible = visibleProfiles();
            const selVisible = visible.filter(p => state.selected.has(p.id)).length;
            const all = document.getElementById('selectAll');
            all.checked = visible.length > 0 && selVisible === visible.length;
            all.indeterminate = selVisible > 0 && selVisible < visible.length;
        }

        function copyId(id) {
            navigator.clipboard.writeText(String(id));
            showAlert('Profile ID copied to clipboard!', 'success');
        }

        // ---- Search & filter ----
        let searchTimer;
        document.getElementById('searchBox').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                state.search = this.value.trim();
                state.page = 1;
                loadProfiles();
            }, 400);
        });

        document.getElementById('filterTabs').addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-filter]');
            if (!btn) return;
            state.filter = btn.dataset.filter;
            render();
        });

        // ---- Alert (sama dengan halaman lain) ----
        function showAlert(message, type = 'success') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    });

                    Toast.fire({
                        icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                        title: message,
                        background: '#111827',
                        color: '#fff'
                    });
                }

        // ---- Init ----
        document.getElementById('newProxyQuickInput').addEventListener('input', parseNewProxyInput);
        ['newProxyHost', 'newProxyPort', 'newProxyLogin', 'newProxyPassword'].forEach(id => {
            document.getElementById(id).addEventListener('input', buildNewProxyInput);
        });
        document.getElementById('proxyModalSelect').addEventListener('change', function() {
            state.proxyCheck = null;
            updateProxyModal();
        });
        document.getElementById('playlistModalSelect').addEventListener('change', updatePlaylistModal);
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && state.proxyModalProfileId !== null) closeProxyModal();
            if (event.key === 'Escape' && state.playlistModalProfileId !== null) closePlaylistModal();
            if (event.key === 'Escape' && !document.getElementById('showPlaylistModal').classList.contains('hidden')) closeShowPlaylistModal();
        });
        loadProfiles();
        loadDolphinProxies();
        loadPlaylists();
        // Sync stream statuses on dashboard entry
        fetch(`${baseUrl}api/stream/all`).catch(err => console.error('Sync error:', err));
        setInterval(() => {
            if (!document.hidden && state.busy.size === 0 && !state.bulkRunning) loadProfiles(true);
        }, REFRESH_MS);
    </script>
</body>

</html>