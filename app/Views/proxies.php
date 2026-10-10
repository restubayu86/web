<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxies - Dolphin{anty} Monitoring</title>
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
                    Proxy Manager
                    <span class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-medium">v2.4 Active</span>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="fetchFromDolphinAPI(this)" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-medium text-sm shadow-lg shadow-purple-500/20 hover:opacity-95 transition-all" title="Fetch proxies from Dolphin Anty account">
                    <i class="bi bi-cloud-download"></i> IMPORT
                </button>
                <button onclick="openAddProxyModal()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">
                    <i class="bi bi-plus-lg"></i> ADD PROXY
                </button>
                <div class="relative">
                    <input type="text" id="searchBox" autocomplete="off" placeholder="Search proxies..." class="px-4 py-2 rounded-lg bg-dark-800 border border-gray-700 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 w-48">
                    <i class="bi bi-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-600"></i>
                </div>
            </div>
        </header>

        <!-- Alert Container -->
        <div id="alertContainer" class="fixed top-6 right-6 z-[100] space-y-3 w-80"></div>

        <!-- Proxies Table -->
        <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-gray-800 bg-dark-900/50">
                        <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            <th class="px-6 py-4 text-left"><input type="checkbox" id="selectAll" class="w-5 h-5 rounded border-2 border-gray-600 text-cyan-500 bg-dark-800 cursor-pointer focus:ring-2 focus:ring-cyan-500 focus:ring-offset-0 hover:border-gray-500 transition-colors"></th>
                            <th class="px-6 py-4 text-left">Name</th>
                            <th class="px-6 py-4 text-left">Status</th>
                            <th class="px-6 py-4 text-left">Profiles</th>
                            <th class="px-6 py-4 text-left">Type</th>
                            <th class="px-6 py-4 text-left">Host</th>
                            <th class="px-6 py-4 text-left">Port</th>
                            <th class="px-6 py-4 text-left">Login</th>
                            <th class="px-6 py-4 text-left">Password</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="proxiesList" class="divide-y divide-gray-800">
                        <tr class="hover:bg-dark-700/50 transition-colors">
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-globe text-4xl mb-3 opacity-20"></i>
                                    <p class="text-sm">No proxies yet. Add your first proxy to get started.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions Bar -->
            <div id="bulkActions" class="hidden fixed bottom-8 left-1/2 -translate-x-1/2 bg-blue-600 shadow-2xl rounded-2xl px-6 py-4 flex items-center gap-4 z-50 animate-slide-up">
                <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-lg">
                    <span id="selectedCount" class="text-lg font-bold text-white">0</span>
                </div>
                <div class="h-8 w-px bg-white/20"></div>
                <button onclick="clearSelection()" class="p-2 hover:bg-white/10 rounded-lg transition-colors" title="Clear selection"><i class="bi bi-arrow-counterclockwise text-white text-xl"></i></button>
                <button onclick="bulkTest()" class="p-2 hover:bg-white/10 rounded-lg transition-colors" title="Bulk test connection"><i class="bi bi-lightning-charge text-white text-xl"></i></button>
                <button onclick="bulkDelete()" class="p-2 hover:bg-white/10 rounded-lg transition-colors" title="Bulk delete"><i class="bi bi-trash text-white text-xl"></i></button>
            </div>
        </div>
    </main>

    <!-- Add/Edit Proxy Modal -->
    <div id="proxyModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-dark-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-gray-700">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-700">
                <div>
                    <h2 id="modalTitle" class="text-2xl font-bold text-white mb-1">New Proxy</h2>
                    <p id="modalSubtitle" class="text-sm text-gray-400">Add a new proxy to your collection</p>
                </div>
                <button onclick="closeProxyModal()" class="text-gray-400 hover:text-white transition-colors"><i class="bi bi-x-lg text-xl"></i></button>
            </div>
            <!-- Modal Body -->
            <div class="p-6 space-y-6" autocomplete="off">
                <!-- Tabs -->
                <div class="flex gap-2 border-b border-gray-700">
                    <button class="px-4 py-3 text-sm font-medium text-cyan-400 border-b-2 border-cyan-400">ADD PROXY</button>
                </div>
                <!-- Quick Input -->
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input type="text" id="quickInput" autocomplete="off" placeholder="Paste proxy (e.g. http://user:pass@host:port)" class="w-full px-4 py-3 bg-dark-900 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    </div>
                    <button type="button" onclick="testQuickProxy()" class="px-4 py-3 rounded-lg bg-dark-700 text-cyan-400 border border-gray-700 hover:bg-dark-600 transition-colors" title="Test connection"><i class="bi bi-lightning-charge"></i></button>
                </div>
                <div id="quickTestStatus" class="hidden p-3 rounded-lg border text-sm"></div>
                <!-- Protocol Selection -->
                <div class="flex gap-2" id="protocolButtons">
                    <button onclick="setProtocol('http')" class="px-4 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 font-medium text-sm" data-proto="http">HTTP</button>
                    <button onclick="setProtocol('socks4')" class="px-4 py-2 rounded-lg bg-dark-700 text-gray-400 hover:text-gray-300 font-medium text-sm transition-colors" data-proto="socks4">SOCKS4</button>
                    <button onclick="setProtocol('socks5')" class="px-4 py-2 rounded-lg bg-dark-700 text-gray-400 hover:text-gray-300 font-medium text-sm transition-colors" data-proto="socks5">SOCKS5</button>
                    <button onclick="setProtocol('ssh')" class="px-4 py-2 rounded-lg bg-dark-700 text-gray-400 hover:text-gray-300 font-medium text-sm transition-colors" data-proto="ssh">SSH</button>
                </div>
                <!-- Form Fields -->
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" id="proxyHost" autocomplete="off" placeholder="Host" class="px-4 py-3 bg-dark-900 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    <input type="text" id="proxyPort" autocomplete="off" placeholder="Port" class="px-4 py-3 bg-dark-900 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" id="proxyLogin" autocomplete="off" placeholder="Login" class="px-4 py-3 bg-dark-900 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    <input type="password" id="proxyPassword" autocomplete="new-password" placeholder="Password" class="px-4 py-3 bg-dark-900 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                </div>
                <input type="text" id="proxyName" autocomplete="off" placeholder="Proxy name" class="w-full px-4 py-3 bg-dark-900 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
            </div>
            <!-- Modal Footer -->
            <div class="flex gap-3 p-6 border-t border-gray-700 justify-end bg-dark-900/50">
                <button onclick="closeProxyModal()" class="px-6 py-3 rounded-lg bg-dark-700 text-gray-300 font-medium hover:bg-dark-600 transition-colors">CANCEL</button>
                <button id="saveProxyBtn" onclick="saveProxy()" class="px-6 py-3 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">ADD PROXY</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const baseUrl = '<?= base_url() ?>';
        let currentEditId = null; // Track current proxy being edited

        document.addEventListener('change', function(e) {
            if (e.target.id === 'selectAll') {
                const checkboxes = document.querySelectorAll('#proxiesList input[type="checkbox"]');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
                updateBulkActions();
            } else if (e.target.closest('#proxiesList')) {
                updateBulkActions();
                const checkboxes = document.querySelectorAll('#proxiesList input[type="checkbox"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const someChecked = Array.from(checkboxes).some(cb => cb.checked);
                const selectAll = document.getElementById('selectAll');
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        });

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('#proxiesList input[type="checkbox"]');
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            const bulkActions = document.getElementById('bulkActions');

            if (selected.length > 0) {
                bulkActions.classList.remove('hidden');
                document.getElementById('selectedCount').innerText = selected.length;
            } else {
                bulkActions.classList.add('hidden');
            }
        }

        // ---------- helpers ----------
        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [c]));
        }

        const sleep = ms => new Promise(r => setTimeout(r, ms));

        // fetch yang TIDAK menelan error: HTTP 4xx/5xx, HTML error page, redirect CSRF -> success:false
        async function apiJson(url, options = {}) {
            try {
                const res = await fetch(url, options);
                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {}
                if (!data) {
                    return {
                        success: false,
                        status: res.status,
                        message: `HTTP ${res.status} - respons bukan JSON (cek route / CSRF / error server)`
                    };
                }
                data.status = res.status;
                if (!res.ok) data.success = false;
                return data;
            } catch (err) {
                return {
                    success: false,
                    status: 0,
                    message: 'Network error: ' + err.message
                };
            }
        }

        function clearSelection() {
            document.querySelectorAll('#proxiesList input[type="checkbox"]').forEach(cb => cb.checked = false);
            const selectAll = document.getElementById('selectAll');
            selectAll.checked = false;
            selectAll.indeterminate = false;
            updateBulkActions();
        }

        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('#proxiesList input[type="checkbox"]');
            const selected = [];
            checkboxes.forEach((cb) => {
                if (cb.checked) {
                    const row = cb.closest('tr');
                    if (row && row.dataset.id) selected.push(row.dataset.id);
                }
            });
            return selected;
        }

        // Hapus satu per satu (BUKAN Promise.all paralel): server dev PHP single-thread,
        // tiap hapus memanggil API Dolphin -> paralel = antrean/timeout/rate-limit.
        async function runBulkDelete(ids, force) {
            const failed = [];
            let ok = 0;

            Swal.fire({
                title: 'Deleting...',
                html: `<span id="bulkProgress">0 / ${ids.length}</span>`,
                background: '#111827',
                color: '#fff',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            for (let i = 0; i < ids.length; i++) {
                const data = await apiJson(`${baseUrl}api/proxy/delete/${ids[i]}${force ? '?force=1' : ''}`, {
                    method: 'DELETE'
                });
                console.log('Bulk delete proxy response:', data);
                if (data.success) {
                    ok++;
                } else {
                    failed.push({
                        id: ids[i],
                        message: data.message || 'Unknown error'
                    });
                }
                const el = document.getElementById('bulkProgress');
                if (el) el.textContent = `${i + 1} / ${ids.length}`;
                if (i < ids.length - 1) await sleep(150); // jaga rate limit Dolphin
            }

            Swal.close();
            clearSelection();
            loadProxies();

            if (failed.length === 0) {
                showAlert(`${ok} proxies deleted successfully`, 'success');
                return;
            }

            const detail = failed.slice(0, 5).map(f => `#${esc(f.id)}: ${esc(f.message)}`).join('<br>');
            const more = failed.length > 5 ? `<br>...and ${failed.length - 5} more` : '';
            const result = await Swal.fire({
                title: `${ok} deleted, ${failed.length} failed`,
                html: `<div style="text-align:left;font-size:13px">${detail}${more}</div>`,
                icon: 'warning',
                background: '#111827',
                color: '#fff',
                showDenyButton: !force,
                denyButtonText: 'Delete locally anyway',
                confirmButtonText: 'OK',
                confirmButtonColor: '#374151',
                denyButtonColor: '#ef4444'
            });
            if (result.isDenied) runBulkDelete(failed.map(f => f.id), true);
        }

        function bulkDelete() {
            const ids = getSelectedIds();
            if (ids.length === 0) return;

            Swal.fire({
                title: 'Are you sure?',
                text: `Delete ${ids.length} selected proxies?`,
                icon: 'warning',
                background: '#111827',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#374151',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) runBulkDelete(ids, false);
            });
        }

        async function bulkTest() {
            const ids = getSelectedIds();
            if (ids.length === 0) return;
            showAlert(`Testing ${ids.length} proxies...`, 'info');
            clearSelection();
            let okCount = 0;
            for (const id of ids) {
                const r = await testProxy(id, true);
                if (r && r.success) okCount++;
            }
            showAlert(`Test done: ${okCount}/${ids.length} working`, okCount === ids.length ? 'success' : 'error');
            loadProxies();
        }

        function loadProxiesFromDolphin() {
            fetch(`${baseUrl}api/proxy/fetch-dolphin`, {
                    method: 'POST'
                })
                .then(res => res.json())
                .then(data => {
                    console.log('Fetch from Dolphin response:', data);
                    if (data.success) {
                        showAlert(`✓ Auto-imported ${data.imported} proxies from Dolphin Anty`, 'success');
                    }
                    loadProxies();
                })
                .catch(err => {
                    console.error('Error fetching from Dolphin:', err);
                    loadProxies();
                });
        }

        function loadProxies() {
            fetch(`${baseUrl}api/proxy/all`)
                .then(res => res.json())
                .then(data => {
                    console.log('Load proxies response:', data);
                    // Mengambil array dari respons (mendukung format paginasi data.data atau langsung array)
                    const proxiesArray = data.data || data;
                    if (proxiesArray && proxiesArray.length > 0) {
                        renderProxies(proxiesArray);
                    } else {
                        document.getElementById('proxiesList').innerHTML = `
                                <tr class="hover:bg-dark-700/50 transition-colors">
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="bi bi-globe text-4xl mb-3 opacity-20"></i>
                                            <p class="text-sm">No proxies yet. Click IMPORT or ADD PROXY to get started.</p>
                                        </div>
                                    </td>
                                </tr>`;
                    }
                })
                .catch(err => {
                    console.error('Error loading proxies:', err);
                    showAlert('Failed to load proxies: ' + err.message, 'error');
                });
        }

        // Fungsi bantuan untuk mengubah teks negara menjadi kode flag-icons (lowercase)
        // Kode 2 huruf ("FR") -> nama lengkap ("France"); selain itu dikembalikan apa adanya
        function countryFullName(v) {
            const raw = String(v || '').trim();
            if (/^[A-Za-z]{2}$/.test(raw)) {
                try {
                    const n = new Intl.DisplayNames(['en'], {
                        type: 'region'
                    }).of(raw.toUpperCase());
                    if (n && n !== raw.toUpperCase()) return n;
                } catch (e) {}
            }
            return raw;
        }

        let countryCodeMap = null;

        // Nama negara ("France") atau kode ("FR") -> kode flag-icons ("fr"). Kosong = tidak ada bendera.
        function getCountryCode(countryName) {
            if (!countryName) return '';
            const raw = String(countryName).trim();
            if (raw === '' || raw.toLowerCase() === 'unknown') return '';
            if (/^[A-Za-z]{2}$/.test(raw)) return raw.toLowerCase();

            if (!countryCodeMap) {
                // alias untuk nama yang berbeda dari data Intl (nama dari ip-api/Dolphin)
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
                    'kosovo': 'xk'
                };
                // kode ISO lama/tidak aktif yang namanya bentrok dengan kode resmi (mis. DD -> Germany)
                const legacy = new Set(['AN', 'BU', 'CS', 'DD', 'DY', 'FX', 'HV', 'NH', 'RH', 'SU', 'TP', 'UK', 'VD', 'YD', 'YU', 'ZR']);
                try {
                    const dn = new Intl.DisplayNames(['en'], {
                        type: 'region'
                    });
                    for (let a = 65; a <= 90; a++) {
                        for (let b = 65; b <= 90; b++) {
                            const code = String.fromCharCode(a, b);
                            if (legacy.has(code)) continue;
                            const name = dn.of(code);
                            if (name && name !== code) {
                                const k = name.toLowerCase();
                                if (!(k in countryCodeMap)) countryCodeMap[k] = code.toLowerCase();
                            }
                        }
                    }
                } catch (e) {}
            }
            return countryCodeMap[raw.toLowerCase()] || '';
        }

        function renderProxies(proxies) {
            const list = document.getElementById('proxiesList');
            list.innerHTML = proxies.map(proxy => {
                const countryName = countryFullName((proxy.lastCheck && proxy.lastCheck.country) ? proxy.lastCheck.country : proxy.country) || 'Unknown';
                const countryCode = getCountryCode(countryName);
                const proxyType = proxy.type ? proxy.type.toUpperCase() : 'HTTP';
                const proxyStatus = (proxy.lastCheck && proxy.lastCheck.status) ? 'working' : (proxy.test_status || 'unknown');

                // If test succeeded and we have a country, show country name as proxy name
                const displayName = (proxy.lastCheck && proxy.lastCheck.country && (proxyStatus === 'working' || proxyStatus === true)) ? proxy.lastCheck.country : (proxy.name || (proxy.type ? proxy.type.toLowerCase() + '://' : 'http://') + (proxy.host || '') + ':' + (proxy.port || ''));

                return `
                <tr class="hover:bg-dark-700/50 transition-colors" data-id="${proxy.id}">
                    <td class="px-6 py-4"><input type="checkbox" class="w-5 h-5 rounded border-2 border-gray-600 text-cyan-500 bg-dark-800 cursor-pointer focus:ring-2 focus:ring-cyan-500 focus:ring-offset-0 hover:border-gray-500 transition-colors"></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="#" onclick="event.preventDefault(); editProxy(${proxy.id})" class="text-cyan-400 hover:text-cyan-300 font-medium cursor-pointer">${esc(displayName)}</a>
                            <button data-copy="${esc(proxy.name || '')}" onclick="navigator.clipboard.writeText(this.dataset.copy); showAlert('Proxy name copied to clipboard!', 'success');" class="text-gray-400 hover:text-gray-200 transition-colors" title="Copy proxy"><i class="bi bi-copy"></i></button>
                        </div>
                        <div class="text-xs text-gray-400 mt-1 flex items-center gap-1.5">
                            ${countryCode ? `<span class="fi fi-${esc(countryCode)} rounded-sm"></span>` : ''}
                            <span>${esc(countryName)}</span>
                            <span class="text-gray-500">•</span>
                            <span>${esc(proxy.ip || proxy.host || '')}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold ${proxyStatus === 'working' || proxyStatus === true ? 'bg-green-500/10 text-green-400' : (proxyStatus === 'failed' ? 'bg-red-500/10 text-red-400' : 'bg-gray-500/10 text-gray-400')}">
                            ${proxyStatus === 'working' || proxyStatus === true ? 'ACTIVE' : (proxyStatus === 'failed' ? 'ERROR' : 'UNKNOWN')}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-400">${proxy.browser_profiles_count || proxy.profiles_count || 0}</td>
                    <td class="px-6 py-4 text-sm"><span class="px-3 py-1 rounded-full bg-blue-500/10 text-blue-400 font-medium text-xs">${proxyType}</span></td>
                    <td class="px-6 py-4 text-sm text-gray-300 font-mono">${esc(proxy.host || 'N/A')}</td>
                    <td class="px-6 py-4 text-sm text-gray-300 font-mono">${esc(proxy.port || 'N/A')}</td>
                    <td class="px-6 py-4 text-sm text-gray-300">${esc(proxy.login || proxy.proxy_login || '-')}</td>
                    <td class="px-6 py-4 text-sm text-gray-300">${esc(proxy.password || proxy.proxy_password || '-')}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button onclick="testProxy(${proxy.id})" class="p-2 rounded-lg bg-dark-700 text-cyan-400 hover:bg-dark-600 transition-colors" title="Test"><i class="bi bi-lightning-charge"></i></button>
                            <button onclick="editProxy(${proxy.id})" class="p-2 rounded-lg bg-dark-700 text-gray-400 hover:bg-dark-600 transition-colors" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button onclick="deleteProxy(${proxy.id})" class="p-2 rounded-lg bg-dark-700 text-red-400 hover:bg-dark-600 transition-colors" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
            updateBulkActions();
        }

        function openAddProxyModal() {
            currentEditId = null;
            document.getElementById('modalTitle').textContent = 'New Proxy';
            document.getElementById('modalSubtitle').textContent = 'Add a new proxy to your collection';
            document.getElementById('saveProxyBtn').textContent = 'ADD PROXY';
            document.getElementById('proxyModal').classList.remove('hidden');
        }

        function closeProxyModal() {
            currentEditId = null;
            document.getElementById('proxyModal').classList.add('hidden');
            document.getElementById('quickInput').value = '';
            document.getElementById('proxyHost').value = '';
            document.getElementById('proxyPort').value = '';
            document.getElementById('proxyLogin').value = '';
            document.getElementById('proxyPassword').value = '';
            document.getElementById('proxyName').value = '';
            document.getElementById('quickTestStatus').classList.add('hidden');
        }

        function setProtocol(proto) {
            document.querySelectorAll('#protocolButtons button').forEach(btn => {
                if (btn.dataset.proto === proto) {
                    btn.className = 'px-4 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 font-medium text-sm';
                } else {
                    btn.className = 'px-4 py-2 rounded-lg bg-dark-700 text-gray-400 hover:text-gray-300 font-medium text-sm transition-colors';
                }
            });
            updateQuickInput();
        }

        const quickInput = document.getElementById('quickInput');
        const fields = ['proxyHost', 'proxyPort', 'proxyLogin', 'proxyPassword'];

        quickInput.addEventListener('input', parseProxyString);
        fields.forEach(id => document.getElementById(id).addEventListener('input', updateQuickInput));

        function parseProxyString() {
            const val = quickInput.value.trim();
            if (!val) return;
            const regex = /^(?:(http|socks4|socks5|ssh):\/\/)?(?:([^:@]+):([^@]+)@)?([^:]+):(\d+)$/i;
            const match = val.match(regex);

            if (match) {
                const [_, type, user, pass, host, port] = match;
                if (type) setProtocol(type.toLowerCase());
                document.getElementById('proxyHost').value = host;
                document.getElementById('proxyPort').value = port;
                document.getElementById('proxyLogin').value = user || '';
                document.getElementById('proxyPassword').value = pass || '';
            }
        }

        function updateQuickInput() {
            const host = document.getElementById('proxyHost').value;
            const port = document.getElementById('proxyPort').value;
            const user = document.getElementById('proxyLogin').value;
            const pass = document.getElementById('proxyPassword').value;
            const proto = document.querySelector('#protocolButtons button.bg-cyan-500\\/10')?.dataset.proto || 'http';

            if (host && port) {
                let str = `${proto}://`;
                str += user && pass ? `${user}:${pass}@` : '';
                str += `${host}:${port}`;
                quickInput.value = str;
            }
        }

        function saveProxy(isTestOnly = false) {
            const formData = new FormData();
            formData.append('proxy_url', `${document.getElementById('proxyHost').value}:${document.getElementById('proxyPort').value}`);
            formData.append('proxy_host', document.getElementById('proxyHost').value);
            formData.append('proxy_port', document.getElementById('proxyPort').value);
            formData.append('proxy_login', document.getElementById('proxyLogin').value);
            formData.append('proxy_password', document.getElementById('proxyPassword').value);
            formData.append('proxy_type', document.querySelector('#protocolButtons button.bg-cyan-500\\/10')?.dataset.proto || 'http');

            const proxyName = document.getElementById('proxyName').value || document.getElementById('quickInput').value || `${document.getElementById('proxyHost').value}:${document.getElementById('proxyPort').value}`;
            formData.append('proxy_name', proxyName);
            formData.append('name', proxyName);

            const isEdit = currentEditId !== null;
            const url = isEdit ? `${baseUrl}api/proxy/update/${currentEditId}` : `${baseUrl}api/proxy/create`;

            fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    console.log('Save proxy response:', data);
                    if (data.success) {
                        if (!isTestOnly) {
                            showAlert(isEdit ? 'Proxy updated successfully' : 'Proxy added successfully', 'success');
                            closeProxyModal();
                            loadProxies();
                        }
                    } else {
                        showAlert(data.message || (isEdit ? 'Error updating proxy' : 'Error adding proxy'), 'error');
                    }
                })
                .catch(err => {
                    console.error('Error saving proxy:', err);
                    showAlert('Error: ' + err.message, 'error');
                });
        }

        async function testProxy(id, silent = false) {
            const data = await apiJson(`${baseUrl}api/proxy/test/${id}`, {
                method: 'POST'
            });
            console.log('Test proxy response:', data);
            if (!silent) {
                showAlert(data.message || 'Test completed', data.success ? 'success' : 'error');
                loadProxies();
            }
            return data;
        }

        function testQuickProxy() {
            const host = document.getElementById('proxyHost').value;
            const port = document.getElementById('proxyPort').value;
            const statusDiv = document.getElementById('quickTestStatus');

            if (!host || !port) {
                showAlert('Please enter host and port first', 'error');
                return;
            }

            statusDiv.className = 'p-3 rounded-lg border text-sm bg-amber-500/10 border-amber-500/30 text-amber-400';
            statusDiv.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Testing connection...';
            statusDiv.classList.remove('hidden');

            const formData = new FormData();
            formData.append('proxy_url', `${host}:${port}`);
            formData.append('proxy_login', document.getElementById('proxyLogin').value);
            formData.append('proxy_password', document.getElementById('proxyPassword').value);
            formData.append('proxy_type', document.querySelector('#protocolButtons button.bg-cyan-500\\/10')?.dataset.proto || 'http');

            fetch(`${baseUrl}api/proxy/test-connection`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
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
                                                icon: 'success',
                                                title: 'Proxy is working! Country: ' + (data.country || 'Unknown'),
                                                background: '#111827',
                                                color: '#fff'
                                            });
                        statusDiv.className = 'p-3 rounded-lg border text-sm bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
                        statusDiv.innerHTML = `<i class="bi bi-check-circle-fill"></i> Working! Country: ${data.country || 'Unknown'}`;
                    } else {
                        statusDiv.className = 'p-3 rounded-lg border text-sm bg-rose-500/10 border-rose-500/30 text-rose-400';
                        statusDiv.innerHTML = `<i class="bi bi-x-circle-fill"></i> Failed: ${data.message}`;
                    }
                })
                .catch(err => {
                    statusDiv.className = 'p-3 rounded-lg border text-sm bg-rose-500/10 border-rose-500/30 text-rose-400';
                    statusDiv.innerHTML = `<i class="bi bi-x-circle-fill"></i> Error: ${err.message}`;
                });
        }

        function editProxy(id) {
            currentEditId = id;

            fetch(`${baseUrl}api/proxy/${id}`)
                .then(res => res.json())
                .then(data => {
                    console.log('Edit proxy fetch response:', data);
                    const proxy = data.data || data;
                    if (proxy) {
                        document.getElementById('modalTitle').textContent = 'Edit Proxy';
                        document.getElementById('modalSubtitle').textContent = `Editing: ${proxy.name || 'Proxy #' + id}`;
                        document.getElementById('saveProxyBtn').textContent = 'UPDATE PROXY';

                        let host = proxy.host || '';
                        let port = proxy.port || '';

                        document.getElementById('proxyHost').value = host;
                        document.getElementById('proxyPort').value = port;
                        document.getElementById('proxyLogin').value = proxy.login || proxy.proxy_login || '';
                        document.getElementById('proxyPassword').value = proxy.password || proxy.proxy_password || '';
                        document.getElementById('proxyName').value = proxy.name || '';
                        document.getElementById('quickInput').value = `${host}:${port}`;

                        const protoType = (proxy.type || 'http').toLowerCase();
                        setProtocol(protoType);

                        document.getElementById('proxyModal').classList.remove('hidden');
                    } else {
                        showAlert('Failed to load proxy data', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error loading proxy data:', err);
                    showAlert('Error loading proxy data: ' + err.message, 'error');
                });
        }

        function deleteProxy(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will not be able to recover this proxy!',
                icon: 'warning',
                background: '#111827',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#374151',
                confirmButtonText: 'Yes, delete it!'
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                const data = await apiJson(`${baseUrl}api/proxy/delete/${id}`, {
                    method: 'DELETE'
                });
                console.log('Delete proxy response:', data);
                if (data.success) {
                    showAlert(data.message || 'Proxy deleted', 'success');
                    loadProxies();
                    return;
                }
                const force = await Swal.fire({
                    title: 'Delete failed',
                    text: data.message || 'Unknown error',
                    icon: 'error',
                    background: '#111827',
                    color: '#fff',
                    showDenyButton: true,
                    denyButtonText: 'Delete locally anyway',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#374151',
                    denyButtonColor: '#ef4444'
                });
                if (force.isDenied) {
                    const d2 = await apiJson(`${baseUrl}api/proxy/delete/${id}?force=1`, {
                        method: 'DELETE'
                    });
                    console.log('Force delete proxy response:', d2);
                    showAlert(d2.message || (d2.success ? 'Proxy deleted' : 'Delete failed'), d2.success ? 'success' : 'error');
                    loadProxies();
                }
            });
        }

        function fetchFromDolphinAPI(btn) {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> IMPORTING...';
            btn.disabled = true;

            fetch(`${baseUrl}api/proxy/fetch-dolphin`, {
                    method: 'POST'
                })
                .then(res => res.json())
                .then(data => {
                    console.log('Fetch from Dolphin API response:', data);
                    if (data.success) {
                        showAlert(`✓ Imported ${data.imported}, updated ${data.updated ?? 0} of ${data.total} proxies`, 'success');
                        loadProxies();
                    } else {
                        showAlert(data.message || 'Failed to fetch from Dolphin API', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error fetching from Dolphin API:', err);
                    showAlert('Error: ' + err.message, 'error');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

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

                document.addEventListener('DOMContentLoaded', loadProxies);
    </script>
</body>

</html>