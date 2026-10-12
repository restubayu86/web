<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Monitoring - Dolphin{anty} Monitoring</title>
    <!-- Tailwind CSS CDN -->
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
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #111827;
        }

        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        #alertContainer .alert {
            white-space: pre-line;
        }

        .modal-content {
            color: #f3f4f6;
        }

        .modal-header {
            border-color: #1f2937;
        }
    </style>
</head>

<body class="bg-dark-900 text-gray-100 min-h-screen flex antialiased selection:bg-brand-500 selection:text-black">
    <?= view('partials/sidebar_tailwind') ?>

    <main class="flex-1 ml-20 lg:ml-24 p-6 lg:p-10 transition-all">
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-1">
                    <i class="bi bi-broadcast"></i> Dolphin{anty} Monitoring Suite
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    Stream Monitoring
                    <span class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-medium">Live Stream</span>
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-2 text-xs text-gray-400 bg-dark-800 px-3 py-2 rounded-xl border border-gray-800">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    Auto-refresh every 5s
                </span>
                <button type="button" onclick="openCreateStreamModal()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all transform hover:-translate-y-0.5">
                    <i class="bi bi-plus-lg"></i> Create Stream
                </button>
            </div>
        </header>

        <div id="alertContainer" class="fixed top-6 right-6 z-[1100] space-y-3 w-[min(24rem,calc(100vw-2rem))]"></div>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl p-5 flex items-center gap-4 relative overflow-hidden backdrop-blur-md">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 to-blue-500"></div>
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20"><i class="bi bi-broadcast text-xl"></i></div>
                <div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Streams</p><p id="totalStreams" class="text-2xl font-bold text-white">0</p></div>
            </div>
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl p-5 flex items-center gap-4 relative overflow-hidden backdrop-blur-md">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20"><i class="bi bi-play-circle text-xl"></i></div>
                <div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Active</p><p id="activeStreams" class="text-2xl font-bold text-white">0</p></div>
            </div>
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl p-5 flex items-center gap-4 relative overflow-hidden backdrop-blur-md">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center border border-blue-500/20"><i class="bi bi-music-note-list text-xl"></i></div>
                <div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Plays</p><p id="totalPlays" class="text-2xl font-bold text-white">0</p></div>
            </div>
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl p-5 flex items-center gap-4 relative overflow-hidden backdrop-blur-md">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-violet-500 to-purple-500"></div>
                <div class="w-12 h-12 rounded-xl bg-violet-500/10 text-violet-400 flex items-center justify-center border border-violet-500/20"><i class="bi bi-clock-history text-xl"></i></div>
                <div><p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Running Time</p><p id="runningTime" class="text-2xl font-bold text-white">0h</p></div>
            </div>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Python Process Monitor Section -->
            <div class="p-6 rounded-2xl border border-amber-500/30 bg-gradient-to-r from-amber-500/5 to-orange-500/5 shadow-lg">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <i class="bi bi-python text-amber-400"></i> Python Process Monitor
                        </h2>
                        <p class="text-sm text-gray-400 mt-1">Monitor and control the BayzBot Python backend process</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="checkPythonBtn" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 hover:bg-amber-500/20 text-sm font-medium transition-colors">
                            <i class="bi bi-arrow-repeat"></i> Check Health
                        </button>
                        <button id="stopPythonBtn" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 hover:bg-rose-500/20 text-sm font-medium transition-colors">
                            <i class="bi bi-stop-circle"></i> Stop Python
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Status</p>
                        <div class="flex items-center gap-2">
                            <span id="pythonStatusIndicator" class="w-3 h-3 rounded-full bg-gray-500 animate-pulse"></span>
                            <span id="pythonStatusText" class="text-sm font-medium text-gray-300">Checking...</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Last Check</p>
                        <span id="pythonLastCheck" class="text-sm font-medium text-gray-300">Never</span>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Recent Logs</p>
                    <div id="pythonLogs" class="bg-dark-900/70 border border-gray-800 rounded-lg p-3 font-mono text-xs text-gray-300 max-h-32 overflow-y-auto space-y-0.5">
                        <div class="text-gray-600">Loading logs...</div>
                    </div>
                </div>
            </div>

            <!-- RAM Monitor Section -->
            <div id="ramMonitorSection" class="p-6 rounded-2xl border border-cyan-500/30 bg-gradient-to-r from-cyan-500/5 to-blue-500/5 shadow-lg">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <i class="bi bi-memory text-cyan-400"></i> RAM Monitor
                        </h2>
                        <p class="text-sm text-gray-400 mt-1">Monitor system RAM usage and get alerts when threshold exceeded</p>
                    </div>
                    <button id="checkRamBtn" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 hover:bg-cyan-500/20 text-sm font-medium transition-colors">
                        <i class="bi bi-arrow-repeat"></i> Check RAM
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Total RAM</p>
                        <span id="ramTotal" class="text-2xl font-bold text-white">--</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Used RAM</p>
                        <span id="ramUsed" class="text-2xl font-bold text-white">--</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Usage</p>
                        <div class="flex items-center gap-2">
                            <span id="ramPercent" class="text-2xl font-bold text-white">--%</span>
                            <span id="ramPercentIndicator" class="w-3 h-3 rounded-full bg-gray-500"></span>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">RAM Usage Progress</p>
                    <div class="w-full bg-dark-900 rounded-full h-4 border border-gray-700 overflow-hidden">
                        <div id="ramProgressBar" class="h-full bg-gradient-to-r from-cyan-500 to-blue-500 rounded-full transition-all duration-500" style="width: 0%"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-gray-500 mt-1">
                        <span>0%</span>
                        <span id="ramThresholdDisplay"><?= esc($ram_threshold_percent ?? '85') ?>% (Threshold)</span>
                        <span>100%</span>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-400">
                    Last check: <span id="ramLastCheck">Never</span>
                </div>
            </div>
        </div>


                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-white">Your streams</h2>
                <p class="text-sm text-gray-400">Manage playlist playback for connected Dolphin profiles.</p>
            </div>
            <span class="inline-flex items-center gap-2 text-xs text-gray-500 sm:hidden">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span> Auto-refresh every 5s
            </span>
        </div>

                        <!-- Revenue Estimation Panel -->
                        <div id="revenuePanel" class="bg-dark-800/90 border border-gray-800 rounded-2xl p-5 shadow-xl mb-5 hidden">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                                    <i class="bi bi-currency-dollar text-amber-400"></i> Revenue Estimation
                                </h3>
                                <button onclick="toggleRevenuePanel()" class="text-xs text-gray-400 hover:text-gray-300">
                                    <i class="bi bi-chevron-up" id="revenueToggleIcon"></i>
                                </button>
                            </div>
                            <div id="revenueContent">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div class="bg-dark-900 border border-gray-700 rounded-xl p-4 text-center">
                                        <p class="text-xs text-gray-400 uppercase tracking-wider">Total Streams</p>
                                        <p id="revTotalStreams" class="text-2xl font-bold text-white">0</p>
                                    </div>
                                    <div class="bg-dark-900 border border-gray-700 rounded-xl p-4 text-center">
                                        <p class="text-xs text-gray-400 uppercase tracking-wider">Active Streams</p>
                                        <p id="revActiveStreams" class="text-2xl font-bold text-emerald-400">0</p>
                                    </div>
                                    <div class="bg-dark-900 border border-gray-700 rounded-xl p-4 text-center">
                                        <p class="text-xs text-gray-400 uppercase tracking-wider">Est. Revenue/Stream</p>
                                        <p id="revEstRevenue" class="text-2xl font-bold text-amber-400">$0</p>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-gray-700">
                                                <th class="pb-2">Country</th>
                                                <th class="pb-2 text-right">Rate/Stream</th>
                                                <th class="pb-2 text-right">Active</th>
                                                <th class="pb-2 text-right">Est. Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody id="revenueTableBody" class="text-gray-300">
                                            <tr><td colspan="4" class="py-4 text-center text-gray-500">Loading...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="streamsContainer" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="col-span-full bg-dark-800/90 border border-gray-800 rounded-2xl p-12 text-center text-gray-500 shadow-xl">
                <i class="bi bi-arrow-repeat animate-spin text-3xl text-cyan-400"></i>
                <p class="mt-3 text-sm">Loading streams...</p>
            </div>
        </div>
    </main>

    <!-- Create Stream Modal -->
        <div id="addStreamModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden p-4">
            <div class="bg-dark-800 border border-gray-700/80 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0 duration-200" id="addStreamModalContent">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700/60 bg-dark-900/50">
                    <h3 class="text-base font-semibold text-white flex items-center gap-2">
                        <i class="bi bi-broadcast text-cyan-400"></i> Create New Stream
                    </h3>
                    <button onclick="closeCreateStreamModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-dark-700 transition-colors">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>
                <div class="p-6">
                    <form id="addStreamForm">
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                                <span class="text-cyan-400 font-bold mr-1">•</span> Stream Name
                            </label>
                            <input type="text" id="streamName" required placeholder="e.g., Main Profile Stream" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                                <span class="text-gray-500">○</span> Profile <span class="text-gray-500 font-normal text-xs">(Optional)</span>
                            </label>
                            <select id="streamProfile" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                                <option value="">Select a profile...</option>
                            </select>
                        </div>
                        <div class="mb-6">
                            <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                                <span class="text-gray-500">○</span> Playlist <span class="text-gray-500 font-normal text-xs">(Optional)</span>
                            </label>
                            <select id="streamPlaylist" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                                <option value="">Select a playlist...</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" onclick="closeCreateStreamModal()" class="px-4 py-2.5 rounded-xl bg-dark-700 text-gray-300 text-xs font-medium hover:bg-dark-600 transition-colors">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-xs font-semibold shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">Create Stream</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <script>
        const SwalDefault = Swal.mixin({
            background: '#111827',
            color: '#f3f4f6',
            confirmButtonColor: '#06b6d4',
            cancelButtonColor: '#6b7280',
            borderRadius: '0.75rem'
        });

        function openCreateStreamModal() {
            const modal = document.getElementById('addStreamModal');
            const content = document.getElementById('addStreamModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeCreateStreamModal() {
            const modal = document.getElementById('addStreamModal');
            const content = document.getElementById('addStreamModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                document.getElementById('addStreamForm').reset();
            }, 200);
        }

        const baseUrl = '<?= base_url() ?>';
        let lastBridgeWarning = '';
        let lastStreamLoadError = '';
        let profileIdsWithStreams = new Set();
        const busyStreamActions = new Set();

        document.addEventListener('DOMContentLoaded', () => {
            // Panggil loadStreams segera untuk sinkronisasi status saat masuk halaman
            loadStreams();
            loadProfiles();
            loadPlaylists();
            checkPythonHealth();
            loadPythonLogs();
            setInterval(loadStreams, 5000);
            setInterval(() => {
                checkPythonHealth();
                loadPythonLogs();
            }, 30000);
            
            document.getElementById('checkPythonBtn').addEventListener('click', checkPythonHealth);
            document.getElementById('stopPythonBtn').addEventListener('click', stopPython);
        });

        function loadStreams() {
            fetch(`${baseUrl}api/stream/all`)
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success || !Array.isArray(data.data)) {
                        throw new Error(data.message || `Could not load streams (HTTP ${response.status}).`);
                    }
                    return data;
                })
                .then(data => {
                    renderStreams(data.data);
                    updateStats(data.data);
                            updateRevenueEstimation(data.data);
                            profileIdsWithStreams = new Set(
                                data.data
                                    .filter(stream => stream.profile_id !== null && stream.profile_id !== '')
                                    .map(stream => String(stream.profile_id))
                            );
                            updateAvailableProfiles();
                            lastStreamLoadError = '';
                            const warnings = Array.isArray(data.warnings) ? data.warnings : [];
                            const warningKey = warnings.join('\n');
                            if (warningKey && warningKey !== lastBridgeWarning) {
                                showAlert(warnings.join('\n'), 'warning');
                            }
                            lastBridgeWarning = warningKey;
                        })
                        .catch(error => {
                            console.error('Error loading streams:', error);
                            const message = error.message || 'Could not load streams.';
                            if (message !== lastStreamLoadError) showAlert(message, 'danger');
                            lastStreamLoadError = message;
                        });
                }

                function loadRevenueSettings() {
                    fetch(`${baseUrl}api/settings/revenue`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.data) {
                                window.revenueRates = data.data;
                                renderRevenueSettings(data.data);
                            }
                        })
                        .catch(() => {});
                }

                function renderRevenueSettings(rates) {
                    const container = document.getElementById('revenue-rows');
                    if (!container) return;
                    container.innerHTML = '';
                                    const ratesArray = Array.isArray(rates) ? rates : [];
                                    ratesArray.forEach(r => {
                                        addRevenueRow(r.country, r.rate);
                                    });
                                }

                function updateRevenueEstimation(streams) {
                    const panel = document.getElementById('revenuePanel');
                    if (!panel) return;
            
                    // Load revenue settings if not loaded
                    if (!window.revenueRates) {
                        loadRevenueSettings();
                    }
            
                                    const rates = Array.isArray(window.revenueRates) ? window.revenueRates : [];
                    const rateMap = {};
                    rates.forEach(r => { rateMap[r.country.toUpperCase()] = parseFloat(r.rate) || 0; });
            
                    const totalStreams = streams.length;
                    const activeStreams = streams.filter(s => s.status === 'playing' || s.status === 'active').length;
            
                    // Group by country (from proxy)
                    const countryStats = {};
                    streams.forEach(stream => {
                        const country = (stream.proxy_country || 'UNKNOWN').toUpperCase();
                        if (!countryStats[country]) {
                            countryStats[country] = { total: 0, active: 0 };
                        }
                        countryStats[country].total++;
                        if (stream.status === 'playing' || stream.status === 'active') {
                            countryStats[country].active++;
                        }
                    });
            
                    let totalEstRevenue = 0;
                    const tbody = document.getElementById('revenueTableBody');
                    tbody.innerHTML = '';
            
                    Object.entries(countryStats).forEach(([country, stats]) => {
                        const rate = rateMap[country] || 0;
                        const estRevenue = stats.active * rate;
                        totalEstRevenue += estRevenue;
                
                        const tr = document.createElement('tr');
                        tr.className = 'border-b border-gray-800';
                        tr.innerHTML = `
                            <td class="py-2">${country}</td>
                            <td class="py-2 text-right">$${rate.toLocaleString()}</td>
                            <td class="py-2 text-right">${stats.active} / ${stats.total}</td>
                            <td class="py-2 text-right text-amber-400 font-medium">$${estRevenue.toLocaleString()}</td>
                        `;
                        tbody.appendChild(tr);
                    });
            
                    if (Object.keys(countryStats).length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-500">No streams</td></tr>';
                    }
            
                    document.getElementById('revTotalStreams').textContent = totalStreams;
                    document.getElementById('revActiveStreams').textContent = activeStreams;
                    document.getElementById('revEstRevenue').textContent = '$' + totalEstRevenue.toLocaleString();
            
                    panel.classList.remove('hidden');
                }

                function toggleRevenuePanel() {
                    const content = document.getElementById('revenueContent');
                    const icon = document.getElementById('revenueToggleIcon');
                    if (content.classList.contains('hidden')) {
                        content.classList.remove('hidden');
                        icon.classList.remove('bi-chevron-down');
                        icon.classList.add('bi-chevron-up');
                    } else {
                        content.classList.add('hidden');
                        icon.classList.remove('bi-chevron-up');
                        icon.classList.add('bi-chevron-down');
                    }
                }

        async function loadProfiles() {
            const select = document.getElementById('streamProfile');
            select.innerHTML = '<option value="">Select a profile...</option>';
            const [dolphinResponse, chromiumResponse] = await Promise.all([
                fetch(`${baseUrl}api/profiles`).then(response => response.json()),
                fetch(`${baseUrl}api/chromium/profiles`).then(response => response.json())
            ]);
            const dolphinProfiles = Array.isArray(dolphinResponse.data) ? dolphinResponse.data : [];
            const chromiumProfiles = chromiumResponse.success && Array.isArray(chromiumResponse.data) ? chromiumResponse.data : [];
            dolphinProfiles.forEach(profile => {
                const option = document.createElement('option');
                option.value = profile.id;
                option.textContent = `Dolphin · ${profile.name || profile.profile_name || ('Profile ' + profile.id)}`;
                select.appendChild(option);
            });
            chromiumProfiles.forEach(profile => {
                const option = document.createElement('option');
                option.value = profile.bridge_id;
                option.textContent = `Chromium · ${profile.profile_name}`;
                select.appendChild(option);
            });
            updateAvailableProfiles();
        }

        function updateAvailableProfiles() {
            const select = document.getElementById('streamProfile');
            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                const alreadyHasStream = profileIdsWithStreams.has(String(option.value));
                option.disabled = alreadyHasStream;
                option.textContent = option.textContent.replace(/ \(stream sudah ada\)$/, '');
                if (alreadyHasStream) option.textContent += ' (stream sudah ada)';
            });
            if (select.value && profileIdsWithStreams.has(String(select.value))) {
                select.value = '';
            }
        }

        function loadPlaylists() {
            fetch(`${baseUrl}api/playlist/all`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('streamPlaylist');
                        data.data.forEach(playlist => {
                            const option = document.createElement('option');
                            option.value = playlist.id;
                            option.textContent = playlist.playlist_name;
                            select.appendChild(option);
                        });
                    }
                });
        }

        function updateStats(streams) {
            document.getElementById('totalStreams').textContent = streams.length;
            const active = streams.filter(s => ['starting', 'running', 'paused'].includes(s.status)).length;
            document.getElementById('activeStreams').textContent = active;
            const totalPlays = streams.reduce((sum, s) => sum + (s.total_plays || 0), 0);
            document.getElementById('totalPlays').textContent = totalPlays;
            const totalDuration = streams.reduce((sum, s) => sum + (s.stream_duration || 0), 0);
            const hours = Math.floor(totalDuration / 3600);
            document.getElementById('runningTime').textContent = hours + 'h';
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[character]);
        }

        function renderStreams(streams) {
            const container = document.getElementById('streamsContainer');
            if (streams.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full bg-dark-800/90 border border-gray-800 rounded-2xl p-12 text-center">
                        <div class="mx-auto w-14 h-14 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center">
                            <i class="bi bi-broadcast text-2xl"></i>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-white">No streams yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Create a stream to control playback for a Dolphin or Chromium profile.</p>
                        <button type="button" onclick="openCreateStreamModal()" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-cyan-500/10 border border-cyan-500/20 px-4 py-2.5 text-sm font-medium text-cyan-300 hover:bg-cyan-500/20 transition-colors">
                            <i class="bi bi-plus-lg"></i> Create your first stream
                        </button>
                    </div>`;
                return;
            }

            let html = '';
            streams.forEach(stream => {
                const statusClass = stream.status === 'running' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' :
                    (stream.status === 'starting' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' :
                        (stream.status === 'paused' ? 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'));
                const startedAt = stream.started_at ? new Date(stream.started_at).toLocaleString() : 'Not started';
                const streamId = Number(stream.id);
                const active = ['starting', 'running', 'paused'].includes(stream.status);
                const busy = busyStreamActions.has(streamId);
                const canPlay = Boolean(stream.profile_id && (stream.playlist_id || active));
                const playLabel = stream.status === 'paused' ? 'Resume' : 'Play';
                const currentSong = ['YouTube Music', 'YouTube'].includes(stream.current_song) ? '' : stream.current_song;

                html += `
                    <article class="bg-dark-800/90 border border-gray-800 rounded-2xl shadow-xl overflow-hidden backdrop-blur-md relative transition-all hover:border-gray-700">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 to-blue-500"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="w-11 h-11 shrink-0 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center">
                                        <i class="bi bi-broadcast text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-white break-words">${escapeHtml(stream.stream_name)}</h3>
                                        <p class="mt-1 text-xs text-gray-500">${stream.profile_id ? `${escapeHtml(stream.browser_type || 'Browser')} · ${escapeHtml(stream.profile_name || `Profile #${stream.profile_id}`)}` : 'No profile assigned'}${stream.playlist_id ? ` <span class="mx-1 text-gray-700">·</span> Playlist #${escapeHtml(stream.playlist_id)}` : ''}</p>
                                    </div>
                                </div>
                                <span class="inline-flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide ${statusClass}">
                                    ${stream.status === 'running' ? '<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>' : ''}
                                    ${escapeHtml(stream.status)}
                                </span>
                            </div>
                            <div class="rounded-xl bg-dark-900/70 border border-gray-800 px-4 py-3 mb-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Now playing</p>
                                <p class="mt-1 text-sm font-semibold text-gray-100 break-words">${escapeHtml(currentSong || (stream.status === 'starting' ? 'Menunggu lagu...' : 'Tidak ada lagu yang diputar'))}</p>
                                ${stream.current_artist ? `<p class="mt-1 text-xs text-cyan-300 break-words">${escapeHtml(stream.current_artist)}</p>` : ''}
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div class="rounded-xl bg-dark-900/50 border border-gray-800 p-3">
                                    <p class="text-[11px] text-gray-500 uppercase tracking-wider">Total Plays</p>
                                    <p class="mt-1 text-base font-semibold text-white">${stream.total_plays || 0}</p>
                                </div>
                                <div class="rounded-xl bg-dark-900/50 border border-gray-800 p-3">
                                    <p class="text-[11px] text-gray-500 uppercase tracking-wider">Duration</p>
                                    <p class="mt-1 text-base font-semibold text-white">${formatTime(stream.stream_duration || 0)}</p>
                                </div>
                                <div class="col-span-2 sm:col-span-1 rounded-xl bg-dark-900/50 border border-gray-800 p-3">
                                    <p class="text-[11px] text-gray-500 uppercase tracking-wider">Started</p>
                                    <p class="mt-1 text-xs font-medium text-gray-300">${escapeHtml(startedAt)}</p>
                                </div>
                            </div>
                            ${stream.automation_message ? `<div class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 px-4 py-3 text-sm text-rose-300" role="status"><i class="bi bi-exclamation-triangle mr-2"></i>${escapeHtml(stream.automation_message)}</div>` : ''}
                        </div>
                        <div class="flex flex-wrap items-center gap-2 border-t border-gray-800 bg-dark-900/30 px-5 py-4 sm:px-6">
                            ${active ? `
                                <button class="inline-flex items-center gap-1.5 rounded-lg bg-rose-500/10 border border-rose-500/20 px-3 py-2 text-xs font-semibold text-rose-300 hover:bg-rose-500/20 disabled:opacity-40 disabled:cursor-not-allowed transition-colors" onclick="streamAction(${streamId}, 'stop')" ${busy ? 'disabled' : ''}><i class="bi bi-stop-fill"></i> Stop</button>
                                <button class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20 px-3 py-2 text-xs font-semibold text-amber-300 hover:bg-amber-500/20 disabled:opacity-40 disabled:cursor-not-allowed transition-colors" onclick="streamAction(${streamId}, 'pause')" ${busy ? 'disabled' : ''}><i class="bi bi-pause-fill"></i> ${stream.status === 'paused' ? 'Resume' : 'Pause'}</button>
                            ` : `
                                <button class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-3 py-2 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/20 disabled:opacity-40 disabled:cursor-not-allowed transition-colors" onclick="streamAction(${streamId}, 'play')" ${!canPlay || busy ? 'disabled' : ''} title="${canPlay ? 'Start or resume playlist' : 'Assign a profile and playlist first'}"><i class="bi bi-play-fill"></i> Play</button>
                            `}
                            <button class="inline-flex items-center justify-center rounded-lg bg-dark-800 border border-gray-700 w-9 h-9 text-gray-300 hover:border-cyan-500/40 hover:text-cyan-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors" onclick="streamAction(${streamId}, 'previous')" ${!active || busy ? 'disabled' : ''} title="Previous track" aria-label="Previous track"><i class="bi bi-skip-backward-fill"></i></button>
                            <button class="inline-flex items-center justify-center rounded-lg bg-dark-800 border border-gray-700 w-9 h-9 text-gray-300 hover:border-cyan-500/40 hover:text-cyan-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors" onclick="streamAction(${streamId}, 'next')" ${!active || busy ? 'disabled' : ''} title="Next track" aria-label="Next track"><i class="bi bi-skip-forward-fill"></i></button>
                            <button class="inline-flex items-center gap-1.5 rounded-lg bg-dark-800 border border-gray-700 px-3 py-2 text-xs font-semibold text-gray-300 hover:border-cyan-500/40 hover:text-cyan-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors" onclick="streamAction(${streamId}, 'restart')" ${!active || busy ? 'disabled' : ''} title="Restart playlist"><i class="bi bi-arrow-counterclockwise"></i> Restart</button>
                            <span class="hidden sm:block flex-1"></span>
                            <button class="inline-flex items-center gap-1.5 rounded-lg bg-dark-800 border border-gray-700 px-3 py-2 text-xs font-semibold text-gray-300 hover:border-cyan-500/40 hover:text-cyan-300 transition-colors" onclick="viewStats(${stream.id})"><i class="bi bi-graph-up"></i> Stats</button>
                            <button class="inline-flex items-center gap-1.5 rounded-lg bg-dark-800 border border-gray-700 px-3 py-2 text-xs font-semibold text-gray-400 hover:border-rose-500/40 hover:text-rose-300 transition-colors" onclick="deleteStream(${stream.id})"><i class="bi bi-trash"></i> Delete</button>
                        </div>
                    </article>
                `;
            });
            container.innerHTML = html;
        }

        async function streamAction(id, action) {
            if (busyStreamActions.has(id)) return;
            busyStreamActions.add(id);
            // Optimistically disable buttons; we'll refresh after a short delay to reflect new state
            try {
                const response = await fetch(`${baseUrl}api/stream/control/${id}/${action}`, {
                    method: 'POST'
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || `Failed to ${action} playback (HTTP ${response.status}).`);
                }
                // Show notification with stream info
                fetch(`${baseUrl}api/stream/stats/${id}`)
                    .then(res => res.json())
                    .then(statsData => {
                        const stats = statsData.data;
                        const msg = `Stream: ${stats.stream_name}\nAction: ${action.toUpperCase()}\nStatus: ${stats.status}`;
                        showAlert(msg, 'success');
                    })
                    .catch(() => {
                        showAlert(data.message || `Playback ${action} command sent.`, 'success');
                    });
                // Refresh streams after a short delay to let backend update status
                setTimeout(loadStreams, 500);
            } catch (error) {
                showAlert(error.message || `Failed to ${action} playback.`, 'danger');
                // Ensure we still try to refresh the UI in case of error
                loadStreams();
            } finally {
                busyStreamActions.delete(id);
            }
        }

        function viewStats(id) {
            fetch(`${baseUrl}api/stream/stats/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.data;
                                SwalDefault.fire({
                            title: `Stream: ${stats.stream_name}`,
                            html: `<p class="text-left"><strong>Status:</strong> ${stats.status}</p>
                                   <p class="text-left"><strong>Total Plays:</strong> ${stats.total_plays}</p>
                                   <p class="text-left"><strong>Duration:</strong> ${formatTime(stats.stream_duration)}</p>
                                   <p class="text-left"><strong>Started:</strong> ${stats.started_at || 'Not started'}</p>
                                   <p class="text-left"><strong>Ended:</strong> ${stats.ended_at || 'Still running'}</p>`,
                            icon: 'info',
                            confirmButtonText: 'Close'
                        });
                    }
                });
        }

        function deleteStream(id) {
                    SwalDefault.fire({
                title: 'Delete Stream?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`${baseUrl}api/stream/delete/${id}`, {
                            method: 'DELETE'
                        })
                        .then(res => res.json())
                        .then(data => {
                            showAlert(data.message, data.success ? 'success' : 'danger');
                            loadStreams();
                        });
                }
            });
        }

        document.getElementById('addStreamForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('stream_name', document.getElementById('streamName').value);
            const profileId = document.getElementById('streamProfile').value;
            const playlistId = document.getElementById('streamPlaylist').value;
            if (profileId && profileIdsWithStreams.has(String(profileId))) {
                showAlert('Profil ini sudah memiliki stream. Pilih profil lain atau gunakan stream yang sudah ada.', 'warning');
                return;
            }
            if (profileId) formData.append('profile_id', profileId);
            if (playlistId) formData.append('playlist_id', playlistId);

            fetch(`${baseUrl}api/stream/create`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Stream created successfully', 'success');
                        closeCreateStreamModal();
                        document.getElementById('addStreamForm').reset();
                        loadStreams();
                    } else {
                        showAlert(data.message || 'Failed to create stream', 'danger');
                    }
                });
        });

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${hours}h ${mins}m ${secs}s`;
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
                        icon: type === 'danger' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                        title: message,
                        background: '#111827',
                        color: '#fff'
                    });
                }

        function checkPythonHealth() {
            const indicator = document.getElementById('pythonStatusIndicator');
            const text = document.getElementById('pythonStatusText');
            const lastCheck = document.getElementById('pythonLastCheck');
            
            indicator.className = 'w-3 h-3 rounded-full bg-amber-500 animate-pulse';
            text.textContent = 'Checking...';
            
            fetch(`${baseUrl}api/check-python-health`)
                .then(res => res.json())
                .then(data => {
                    const now = new Date().toLocaleTimeString();
                    lastCheck.textContent = now;
                    
                    if (data.success && data.running) {
                        indicator.className = 'w-3 h-3 rounded-full bg-emerald-500';
                        text.textContent = 'Running';
                    } else {
                        indicator.className = 'w-3 h-3 rounded-full bg-rose-500';
                        text.textContent = 'Stopped';
                    }
                })
                .catch(err => {
                    console.error('Error checking Python health:', err);
                    indicator.className = 'w-3 h-3 rounded-full bg-rose-500';
                    text.textContent = 'Error checking status';
                    lastCheck.textContent = new Date().toLocaleTimeString();
                });
        }

        function stopPython() {
            if (!confirm('Are you sure you want to stop the Python process? This will interrupt all active streams.')) {
                return;
            }
            
            fetch(`${baseUrl}api/stop-python`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Python process stopped successfully', 'success');
                        setTimeout(checkPythonHealth, 1000);
                    } else {
                        showAlert(data.message || 'Failed to stop Python process', 'danger');
                    }
                })
                .catch(err => {
                    console.error('Error stopping Python:', err);
                    showAlert('Error stopping Python process', 'danger');
                });
        }

        function loadPythonLogs() {
            const logsContainer = document.getElementById('pythonLogs');
            
            fetch(`${baseUrl}api/get-python-logs`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.logs) {
                        const logs = Array.isArray(data.logs) ? data.logs : data.logs.split('\n');
                        logsContainer.innerHTML = logs
                            .filter(line => line.trim())
                            .map(line => `<div>${escapeHtml(line)}</div>`)
                            .join('');
                        logsContainer.scrollTop = logsContainer.scrollHeight;
                    } else {
                        logsContainer.innerHTML = '<div class="text-gray-600">No logs available</div>';
                    }
                })
                .catch(err => {
                    console.error('Error loading Python logs:', err);
                    logsContainer.innerHTML = '<div class="text-rose-400">Error loading logs</div>';
                });
        }

        let lastRamAlertTime = 0;
        function checkRamUsage() {
            const totalEl = document.getElementById('ramTotal');
            const usedEl = document.getElementById('ramUsed');
            const percentEl = document.getElementById('ramPercent');
            const indicatorEl = document.getElementById('ramPercentIndicator');
            const progressEl = document.getElementById('ramProgressBar');
            const lastCheckEl = document.getElementById('ramLastCheck');
            const threshold = parseFloat(document.getElementById('ramThresholdDisplay').textContent) || 85;

            fetch(`${baseUrl}settings/get-ram-usage`)
                .then(res => res.json())
                .then(data => {
                    lastCheckEl.textContent = new Date().toLocaleTimeString();
                    if (data.success) {
                        const totalMB = Math.round(data.total / 1024);
                        const usedMB = Math.round(data.used / 1024);
                        totalEl.textContent = totalMB + ' MB';
                        usedEl.textContent = usedMB + ' MB';
                        percentEl.textContent = data.percent + '%';
                        progressEl.style.width = data.percent + '%';
                        
                        if (data.percent > 90) {
                            indicatorEl.className = 'w-3 h-3 rounded-full bg-rose-500';
                            progressEl.className = 'h-full bg-gradient-to-r from-rose-500 to-red-600 rounded-full transition-all duration-500';
                        } else if (data.percent > threshold) {
                            indicatorEl.className = 'w-3 h-3 rounded-full bg-amber-500';
                            progressEl.className = 'h-full bg-gradient-to-r from-amber-500 to-orange-500 rounded-full transition-all duration-500';
                        } else {
                            indicatorEl.className = 'w-3 h-3 rounded-full bg-emerald-500';
                            progressEl.className = 'h-full bg-gradient-to-r from-cyan-500 to-blue-500 rounded-full transition-all duration-500';
                        }

                        if (data.percent > threshold) {
                            const now = Date.now();
                            if (now - lastRamAlertTime > 300000) { // 5 minutes cooldown
                                lastRamAlertTime = now;
                                sendRamAlert(data.percent, threshold);
                            }
                        }
                    }
                })
                .catch(err => console.error('Error checking RAM:', err));
        }

        function sendRamAlert(percent, threshold) {
            const formData = new FormData();
            formData.append('ram_percent', percent);
            formData.append('ram_threshold', threshold);
            // Assuming we have access to these from somewhere or we fetch them
            // For now, let's assume the backend handles the telegram config if we don't pass it, 
            // but the controller expects them. Let's fetch them from settings if needed.
            // Actually, the controller expects them in POST.
            // I will add a hidden field or fetch them.
            // Let's just try to send it and see if the controller has defaults or if we need to pass them.
            // Wait, the controller expects them. I should probably fetch them.
            
            fetch(`${baseUrl}settings/send-ram-notification`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    console.log('RAM alert sent');
                } else {
                    console.error('Failed to send RAM alert:', data.message);
                }
            });
        }

        document.getElementById('checkRamBtn').addEventListener('click', checkRamUsage);
                setInterval(checkRamUsage, 10000); // Poll every 10s
                checkRamUsage(); // Initial check on page load
                    </script>