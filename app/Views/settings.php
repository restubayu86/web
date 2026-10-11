<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Dolphin{anty} Monitoring</title>
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
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--single {
            background-color: #0b0f19 !important;
            border: 1px solid #374151 !important;
            height: 42px !important;
            padding: 6px !important;
            color: #e5e7eb !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #e5e7eb !important;
            line-height: 28px !important;
        }
        .select2-dropdown {
            background-color: #111827 !important;
            border: 1px solid #374151 !important;
            color: #e5e7eb !important;
        }
        .select2-results__option {
            background-color: #111827 !important;
            color: #e5e7eb !important;
        }
        .select2-results__option--highlighted {
            background-color: #06b6d4 !important;
            color: white !important;
        }
        .select2-search__field {
            background-color: #0b0f19 !important;
            color: #e5e7eb !important;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Custom scrollbar for dark UI */
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
    </style>
</head>

<body class="bg-dark-900 text-gray-100 min-h-screen flex antialiased selection:bg-brand-500 selection:text-black">

    <?= view('partials/sidebar_tailwind') ?>

    <!-- Main Content Area -->
    <main class="flex-1 ml-20 lg:ml-24 p-6 lg:p-10 transition-all">
        <!-- Top Header & Alert Notification Area -->
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-1">
                    <i class="bi bi-broadcast"></i> Dolphin{anty} Monitoring Suite
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    System Settings
                    <span class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-medium">Dolphin{anty} v2.8</span>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= site_url('settings/tutorial') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-dark-700 border border-gray-700 text-cyan-300 font-medium text-sm hover:bg-dark-600 transition-all">
                    <i class="bi bi-book"></i> Tutorial
                    <i class="bi bi-box-arrow-up-right text-xs"></i>
                </a>
                <button type="submit" form="settings-form" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all transform hover:-translate-y-0.5">
                    <i class="bi bi-save"></i> Save Settings
                </button>
            </div>
        </header>
        <!-- Flash Message / Alert Container -->
        <?php if (function_exists('session') && session()->getFlashdata("message")): ?>
            <?php
            $msg = session()->getFlashdata("message");
            $msgText = is_array($msg) ? json_encode($msg) : (string)$msg;
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: '<?= esc($msgText) ?>',
                        background: '#111827',
                        color: '#fff',
                        confirmButtonColor: '#06b6d4',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                });
            </script>
        <?php endif; ?>
        <?php if (function_exists('session') && session()->getFlashdata("error")): ?>
            <?php
            $err = session()->getFlashdata("error");
            $errText = is_array($err) ? json_encode($err) : (string)$err;
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Terjadi Kesalahan',
                        text: '<?= esc($errText) ?>',
                        background: '#111827',
                        color: '#fff',
                        confirmButtonColor: '#ef4444',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                });
            </script>
        <?php endif; ?>

        <form id="settings-form" method="post" action="/settings/save" autocomplete="off">
            <?= csrf_field() ?>

            <!-- Python Health Monitoring Section -->
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl p-6 lg:p-8 shadow-xl backdrop-blur-md mb-8 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-yellow-500 via-orange-500 to-red-500"></div>
                <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-800/80">
                    <div class="w-10 h-10 rounded-xl bg-yellow-500/10 text-yellow-400 flex items-center justify-center border border-yellow-500/20">
                        <i class="bi bi-cpu text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-white">Python Process Monitor</h2>
                        <p class="text-xs text-gray-400">Monitor dan kontrol proses Python BayzBot</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column: Status Pemeriksaan -->
                    <div class="space-y-4">
                        <div class="mb-6">
                            <label class="text-xs font-semibold text-gray-300 uppercase tracking-wider mb-3 block">Status Pemeriksaan</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" id="connect-all-settings" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm shadow-lg shadow-emerald-500/20 transition-all">
                                    <i class="bi bi-plug"></i> Hubungkan Semua
                                </button>
                            </div>
                        </div>
                        <section id="connect-all-results" class="hidden mb-6 rounded-2xl border border-gray-800 bg-dark-800/90 p-5" aria-live="polite">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div>
                                    <h2 id="connect-all-title" class="font-semibold text-white">Pemeriksaan konfigurasi</h2>
                                    <p id="connect-all-summary" class="text-xs text-gray-400 mt-1"></p>
                                </div>
                                <span id="connect-all-spinner" class="hidden w-4 h-4 shrink-0 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin" aria-label="Sedang memeriksa"></span>
                            </div>
                            <ul id="connect-all-checks" class="space-y-2 text-sm"></ul>
                        </section>

                    </div>

                    <!-- Right Column: Python Logs Viewer -->
                    <div class="flex flex-col h-full">
                        <div class="mb-4">
                            <label class="text-xs font-semibold text-gray-300 uppercase tracking-wider mb-3 block">Status Pemeriksaan</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button type="button" id="check-python-health" onclick="checkHealth()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 font-medium text-sm border border-yellow-500/30 transition-all">
                                    <i class="bi bi-heart-pulse"></i> Check Health
                                </button>
                                <button type="button" id="stop-python-process" onclick="stopAll()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 font-medium text-sm border border-red-500/30 transition-all">
                                    <i class="bi bi-stop-circle"></i> Stop Semua
                                </button>
                            </div>
                        </div>

                        <!-- Status Display -->
                        <div id="python-health-status" class="hidden p-4 rounded-xl border border-gray-700 bg-dark-900/50 transition-all">
                            <div class="flex items-center gap-2 mb-2">
                                <div id="python-status-indicator" class="w-3 h-3 rounded-full bg-gray-500"></div>
                                <span id="python-status-text" class="text-xs font-medium">Checking...</span>
                            </div>
                            <p id="python-status-message" class="text-xs text-gray-400 leading-relaxed"></p>
                        </div>

                        <div class="mb-4 p-4 rounded-xl bg-blue-500/5 border border-blue-500/20">
                            <p class="text-[11px] text-blue-300/80 leading-relaxed">
                                <i class="bi bi-info-circle mr-1"></i>
                                Gunakan <strong>Check Health</strong> untuk memastikan backend Python merespons. Jika stuck, gunakan <strong>Stop All</strong> untuk membersihkan PID yang menggantung.
                            </p>
                        </div>

                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-300 uppercase tracking-wider">Watchdog / Python Logs</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" id="copy-python-logs" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-dark-700 hover:bg-dark-600 text-gray-300 text-xs border border-gray-700 transition-all" title="Copy Logs">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                                <button type="button" id="clear-python-logs-ui" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-dark-700 hover:bg-dark-600 text-gray-300 text-xs border border-gray-700 transition-all" title="Clear View">
                                    <i class="bi bi-trash"></i> Clear
                                </button>
                                <button type="button" id="refresh-python-logs" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-dark-700 hover:bg-dark-600 text-cyan-400 text-xs border border-gray-700 transition-all">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>
                        <div id="python-logs-container" class="flex-1 bg-dark-900 border border-gray-700 rounded-xl p-4 min-h-[200px] max-h-[300px] overflow-y-auto font-mono text-[11px] text-gray-400 space-y-1 shadow-inner">
                            <p class="text-gray-600 italic">Loading logs...</p>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-2 italic">Menampilkan 250 baris terakhir dari log aktivitas backend.</p>
                    </div>
                </div>
            </div>

            <!-- Configuration Sections -->
            <div class="space-y-8 mb-8">
                <!-- Panel 1: Dolphin Anty API Configuration -->
                <div class="bg-dark-800/90 border border-gray-800 rounded-2xl p-6 lg:p-8 shadow-xl backdrop-blur-md relative overflow-hidden flex flex-col">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500"></div>

                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-800/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                                <i class="bi bi-browser-chrome text-lg"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-white">Dolphin{anty} API Configuration</h2>
                                <p class="text-xs text-gray-400">Manage your endpoint connectivity and authentication token</p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">API Connection Settings</label>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">API URL Endpoint</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-500">
                                        <i class="bi bi-globe"></i>
                                    </span>
                                    <input type="text" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="api_url" id="api_url" value="https://anty-api.com" readonly autocomplete="off">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">API Token Key</label>
                                <input type="password" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all" name="api_token" id="api_token" value="<?= esc($api_token ?? '') ?>" placeholder="Masukkan API Token Dolphin Anty" autocomplete="new-password">
                                <p class="text-[11px] text-gray-500 mt-1.5">Token autentikasi rahasia untuk mengakses browser profiles via API.</p>
                            </div>
                            <div class="flex items-end pb-2 mb-4">
                                <button type="button" id="check-connection" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-dark-700 hover:bg-dark-600 text-cyan-400 font-medium text-sm border border-gray-700 transition-all shadow-sm">
                                    <i class="bi bi-wifi"></i> Check
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Local API (Start / Stop Profil)</label>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-3">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Local API URL</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-500">
                                        <i class="bi bi-hdd-network"></i>
                                    </span>
                                    <input type="text" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="local_api_url" id="local_api_url" value="<?= esc($local_api_url ?? 'http://localhost:3001') ?>" placeholder="http://localhost:3001" autocomplete="off">
                                </div>
                            </div>
                            <div class="flex items-end">
                                <button type="button" id="check-local" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-dark-700 hover:bg-dark-600 text-cyan-400 font-medium text-sm border border-gray-700 transition-all shadow-sm">
                                    <i class="bi bi-hdd-network"></i> Check
                                </button>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1.5">Aplikasi Dolphin{anty} harus berjalan di mesin yang sama dengan server ini. Port tampil di jendela Health (default 3001). Hanya localhost / 127.0.0.1.</p>
                    </div>

                    <div class="mt-auto pt-4 border-t border-gray-800/80 flex items-center justify-between">
                        <a href="https://dolphin-anty.com/panel/index.html#/api" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-cyan-500/10 text-cyan-400 text-xs font-semibold border border-cyan-500/20 hover:bg-cyan-500/20 transition-all">
                            <i class="bi bi-box-arrow-up-right"></i> Get API Token from Dolphin{anty}
                        </a>

                        <div id="connection-status" class="hidden items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium border transition-all">
                            <div id="checking-spinner" class="hidden w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                            <span id="status-text">Not checked</span>
                        </div>
                    </div>
                </div>

                <!-- Panel 2: BayzBot Python Bridge -->
                <div class="bg-dark-800/90 border border-gray-800 rounded-2xl p-6 lg:p-8 shadow-xl backdrop-blur-md relative overflow-hidden flex flex-col">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-cyan-500 to-blue-500"></div>
                    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-800/80">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                            <i class="bi bi-link-45deg text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-white">BayzBot Python Bridge</h2>
                            <p class="text-xs text-gray-400">Arahkan playback ke ekstensi yang dipetakan ke profil Dolphin yang tepat.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-medium text-gray-300 mb-1.5" for="bot_bridge_url">URL Bridge</label>
                            <input type="text" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="bot_bridge_url" id="bot_bridge_url" value="<?= esc($bot_bridge_url ?? 'http://127.0.0.1:8766') ?>" placeholder="http://127.0.0.1:8766" autocomplete="off">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-300 mb-1.5" for="bot_bridge_token">Token Web API</label>
                            <input type="password" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="bot_bridge_token" id="bot_bridge_token" value="<?= esc($bot_bridge_token ?? '') ?>" placeholder="Salin dari Options BayzBot" autocomplete="new-password">
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="check-bot-bridge" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-dark-700 hover:bg-dark-600 text-emerald-400 font-medium text-sm border border-gray-700 transition-all shadow-sm">
                                <i class="bi bi-plug"></i> Test Bridge
                            </button>
                        </div>
                    </div>
                    <div id="bot-bridge-status" class="hidden mt-4 items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium border transition-all">
                        <span id="bot-bridge-status-text"></span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-auto pt-4">Pada setiap profil Dolphin, buka Settings ekstensi dan isi Dolphin Browser Profile ID sesuai ID profil. Pengaturan loop, acak, watchdog, dan jeda skip iklan disinkronkan saat playback dimulai.</p>
                </div>

                <!-- Panel 3: Telegram Bot Configuration -->
                <div class="bg-dark-800/90 border border-gray-800 rounded-2xl p-6 lg:p-8 shadow-xl backdrop-blur-md flex flex-col relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500"></div>

                    <div>
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-800/80">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                                    <i class="bi bi-telegram text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-white">Telegram Bot Setup</h2>
                                    <p class="text-xs text-gray-400">Configure bot notifications and alerts</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="mb-4 md:col-span-1">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Target Chat ID</label>
                                <input type="text" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="telegram_chat_id" id="telegram_chat_id" value="<?= esc($telegram_chat_id ?? '') ?>" placeholder="Masukkan Chat ID" autocomplete="off">
                            </div>
                            <div class="mb-4 md:col-span-2">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Bot Token</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-500">
                                        <i class="bi bi-key"></i>
                                    </span>
                                    <input type="password" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all" name="telegram_bot_token" id="telegram_bot_token" value="<?= esc($telegram_bot_token ?? '') ?>" placeholder="Masukkan Bot Token dari @BotFather" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="flex items-end pb-4">
                                <button type="button" id="test-telegram-bot" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-dark-700 hover:bg-dark-600 text-emerald-400 font-medium text-sm border border-gray-700 transition-all shadow-sm">
                                    <i class="bi bi-send"></i> Test Bot Connection
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 border-t border-gray-800 py-2">
                            <div class="p-3 rounded-xl bg-dark-900 border border-gray-800 flex items-center justify-between">
                                <div>
                                    <label for="telegram_notifications_enabled" class="text-xs font-semibold text-gray-200 block cursor-pointer">Aktifkan Notifikasi</label>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="telegram_notifications_enabled" name="telegram_notifications_enabled" value="1" <?= ($telegram_notifications_enabled ?? '0') === '1' ? 'checked' : '' ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-dark-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-4">
                            <div class="md:col-span-1 mb-4">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Threshold RAM (%)</label>
                                <div class="relative">
                                    <input type="number" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all font-mono" name="ram_threshold_percent" id="ram_threshold_percent" value="<?= esc($ram_threshold_percent ?? '85') ?>" placeholder="85" min="1" max="100" autocomplete="off">
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-500">%</span>
                                </div>
                            </div>

                            <div class="md:col-span-3 mb-4 p-4 rounded-xl bg-dark-900 border border-gray-800 flex items-center justify-between">
                                <div>
                                    <label for="telegram_ram_notifications_enabled" class="text-xs font-semibold text-gray-200 block cursor-pointer">Notifikasi RAM Tinggi</label>
                                    <span class="text-[11px] text-gray-500">Kirim notifikasi Telegram saat penggunaan RAM melebihi threshold</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="telegram_ram_notifications_enabled" name="telegram_ram_notifications_enabled" value="1" <?= ($telegram_ram_notifications_enabled ?? '0') === '1' ? 'checked' : '' ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-dark-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                </label>
                            </div>

                            <div class="md:col-span-4 mb-4 p-4 rounded-xl bg-dark-900 border border-gray-800 flex items-center justify-between">
                                <div>
                                    <label for="ram_stop_streams_enabled" class="text-xs font-semibold text-gray-200 block cursor-pointer">Hentikan Semua Stream Jika RAM Melebihi Threshold</label>
                                    <span class="text-[11px] text-gray-500">Otomatis menghentikan semua stream aktif saat penggunaan RAM melebihi batas yang ditentukan</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="ram_stop_streams_enabled" name="ram_stop_streams_enabled" value="1" <?= ($ram_stop_streams_enabled ?? '0') === '1' ? 'checked' : '' ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-dark-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                                </label>
                            </div>
                        </div>

                    </div>

                    <div class="mt-auto pt-4 border-t border-gray-800/80 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <a href="https://t.me/botfather" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-cyan-500/10 text-cyan-400 text-xs font-semibold border border-cyan-500/20 hover:bg-cyan-500/20 transition-all">
                            <i class="bi bi-box-arrow-up-right"></i> Buat Bot di @BotFather
                        </a>

                        <div id="telegram-status" class="hidden items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium border transition-all">
                            <div id="telegram-spinner" class="hidden w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                            <span id="telegram-status-text">Not tested</span>
                        </div>
                    </div>
                </div>

                <!-- Panel 4: Playlist & Monitoring Settings -->
                <div class="bg-dark-800/90 border border-gray-800 rounded-2xl p-6 lg:p-8 shadow-xl backdrop-blur-md flex flex-col relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500"></div>

                    <div>
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-800/80">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                                    <i class="bi bi-play-circle text-lg"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-white">Playlist & Monitoring</h2>
                                    <p class="text-xs text-gray-400">Configure playback cycles and watchdog rules</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-8 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Total Loop Playlist</label>
                                <div class="relative">
                                    <input type="number" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="total_loop" id="total_loop" value="<?= esc($total_loop ?? '-1') ?>" placeholder="Masukkan jumlah loop" autocomplete="off">
                                </div>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Interval Watchdog (detik)</label>
                                <input type="number" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="watchdog_interval" id="watchdog_interval" value="<?= esc($watchdog_interval ?? '30') ?>" min="5" step="1" placeholder="30" autocomplete="off">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Skip Iklan Delay (detik)</label>
                                <input type="number" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-mono" name="ad_skip_delay_seconds" id="ad_skip_delay_seconds" value="<?= esc($ad_skip_delay_seconds ?? '0') ?>" min="0" step="0.5" placeholder="0" autocomplete="off">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-300 mb-1.5">Acak Playlist (Round Robin)</label>
                                <div class="p-3 rounded-xl bg-dark-900 border border-gray-800 flex items-center justify-between">
                                    <span class="text-[11px] text-gray-500">Putar urutan playlist menggunakan algoritma acak</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="shuffle_playlist" name="shuffle_playlist" value="1" <?= ($shuffle_playlist ?? '0') === '1' ? 'checked' : '' ?> class="sr-only peer">
                                        <div class="w-11 h-6 bg-dark-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Estimation Section -->
                    <div class="mt-8 pt-6 border-t border-gray-800">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-white">Revenue Estimation (per Stream)</h3>
                            <button type="button" onclick="addRevenueRow()" class="text-xs text-cyan-400 hover:text-cyan-300 font-medium">
                                <i class="bi bi-plus-circle"></i> Add Country
                            </button>
                        </div>
                        <div id="revenue-rows" class="space-y-3">
                            <!-- Rows will be injected here -->
                        </div>
                    </div>

                                        <script>
                                            // Default countries for Select2
                                            const defaultCountries = [
                                                { code: 'US', name: 'United States' },
                                                { code: 'GB', name: 'United Kingdom' },
                                                { code: 'DE', name: 'Germany' },
                                                { code: 'FR', name: 'France' },
                                                { code: 'CA', name: 'Canada' },
                                                { code: 'AU', name: 'Australia' },
                                                { code: 'JP', name: 'Japan' },
                                                { code: 'SG', name: 'Singapore' },
                                                { code: 'NL', name: 'Netherlands' },
                                                { code: 'BR', name: 'Brazil' },
                                                { code: 'IN', name: 'India' },
                                                { code: 'ID', name: 'Indonesia' },
                                                { code: 'MY', name: 'Malaysia' },
                                                { code: 'TH', name: 'Thailand' },
                                                { code: 'VN', name: 'Vietnam' },
                                                { code: 'PH', name: 'Philippines' },
                                                { code: 'KR', name: 'South Korea' },
                                                { code: 'HK', name: 'Hong Kong' },
                                                { code: 'TW', name: 'Taiwan' },
                                                { code: 'MX', name: 'Mexico' },
                                                { code: 'AR', name: 'Argentina' },
                                                { code: 'CL', name: 'Chile' },
                                                { code: 'CO', name: 'Colombia' },
                                                { code: 'PE', name: 'Peru' },
                                                { code: 'ZA', name: 'South Africa' },
                                                { code: 'NG', name: 'Nigeria' },
                                                { code: 'KE', name: 'Kenya' },
                                                { code: 'EG', name: 'Egypt' },
                                                { code: 'AE', name: 'United Arab Emirates' },
                                                { code: 'SA', name: 'Saudi Arabia' },
                                                { code: 'IL', name: 'Israel' },
                                                { code: 'TR', name: 'Turkey' },
                                                { code: 'RU', name: 'Russia' },
                                                { code: 'PL', name: 'Poland' },
                                                { code: 'CZ', name: 'Czech Republic' },
                                                { code: 'AT', name: 'Austria' },
                                                { code: 'CH', name: 'Switzerland' },
                                                { code: 'BE', name: 'Belgium' },
                                                { code: 'IE', name: 'Ireland' },
                                                { code: 'NO', name: 'Norway' },
                                                { code: 'SE', name: 'Sweden' },
                                                { code: 'DK', name: 'Denmark' },
                                                { code: 'FI', name: 'Finland' },
                                                { code: 'PT', name: 'Portugal' },
                                                { code: 'ES', name: 'Spain' },
                                                { code: 'IT', name: 'Italy' },
                                                { code: 'GR', name: 'Greece' },
                                                { code: 'RO', name: 'Romania' },
                                                { code: 'HU', name: 'Hungary' },
                                                { code: 'SK', name: 'Slovakia' },
                                                { code: 'SI', name: 'Slovenia' },
                                                { code: 'HR', name: 'Croatia' },
                                                { code: 'RS', name: 'Serbia' },
                                                { code: 'BG', name: 'Bulgaria' },
                                                { code: 'LT', name: 'Lithuania' },
                                                { code: 'LV', name: 'Latvia' },
                                                { code: 'EE', name: 'Estonia' },
                                                { code: 'UA', name: 'Ukraine' },
                                                { code: 'BY', name: 'Belarus' },
                                                { code: 'KZ', name: 'Kazakhstan' },
                                                { code: 'UZ', name: 'Uzbekistan' },
                                                { code: 'MN', name: 'Mongolia' },
                                                { code: 'PK', name: 'Pakistan' },
                                                { code: 'BD', name: 'Bangladesh' },
                                                { code: 'LK', name: 'Sri Lanka' },
                                                { code: 'NP', name: 'Nepal' },
                                                { code: 'MM', name: 'Myanmar' },
                                                { code: 'KH', name: 'Cambodia' },
                                                { code: 'LA', name: 'Laos' },
                                                { code: 'BN', name: 'Brunei' },
                                                { code: 'TL', name: 'Timor-Leste' },
                                                { code: 'NZ', name: 'New Zealand' },
                                                { code: 'FJ', name: 'Fiji' },
                                                { code: 'PG', name: 'Papua New Guinea' },
                                                { code: 'SB', name: 'Solomon Islands' },
                                                { code: 'VU', name: 'Vanuatu' },
                                                { code: 'WS', name: 'Samoa' },
                                                { code: 'TO', name: 'Tonga' },
                                                { code: 'TV', name: 'Tuvalu' },
                                                { code: 'NR', name: 'Nauru' },
                                                { code: 'PW', name: 'Palau' },
                                                { code: 'MH', name: 'Marshall Islands' },
                                                { code: 'FM', name: 'Micronesia' },
                                                { code: 'KI', name: 'Kiribati' }
                                            ];

                                            function createCountryOptions(selectedCode = '') {
                                                let options = '<option value="">Select Country</option>';
                                                defaultCountries.forEach(c => {
                                                    const sel = c.code === selectedCode ? 'selected' : '';
                                                    options += `<option value="${c.code}" ${sel}>${c.code} - ${c.name}</option>`;
                                                });
                                                return options;
                                            }

                                            function addRevenueRow(country = '', rate = '') {
                                                const container = document.getElementById('revenue-rows');
                                                const div = document.createElement('div');
                                                div.className = 'grid grid-cols-3 gap-3 revenue-row';
                                                div.innerHTML = `
                                                    <select name="rev_country[]" class="country-select bg-dark-900 border border-gray-700 rounded-xl px-3 py-2 text-sm text-gray-200" style="width: 100%;">${createCountryOptions(country)}</select>
                                                    <input type="number" name="rev_rate[]" placeholder="Rate" value="${rate}" class="bg-dark-900 border border-gray-700 rounded-xl px-3 py-2 text-sm text-gray-200" step="0.01" min="0">
                                                    <button type="button" onclick="this.closest('.revenue-row').remove()" class="text-red-400 hover:text-red-300 text-xs self-center"><i class="bi bi-trash"></i></button>
                                                `;
                                                container.appendChild(div);
                            
                                                // Initialize Select2 on the new select
                                                $(div).find('.country-select').select2({
                                                    dropdownParent: $(div),
                                                    width: '100%',
                                                    placeholder: 'Select Country',
                                                    allowClear: true
                                                });
                                            }

                                            // Initialize existing rows on page load
                                            document.addEventListener('DOMContentLoaded', function() {
                                                // Load existing revenue rates from PHP variable
                                                const revenueRates = <?= json_encode($revenue_rates ?? []) ?>;
                                                if (revenueRates && revenueRates.length > 0) {
                                                    revenueRates.forEach(r => {
                                                        addRevenueRow(r.country, r.rate);
                                                    });
                                                } else {
                                                    // Add one empty row by default
                                                    addRevenueRow();
                                                }
                                            });
                                        </script>
                </div>
            </div>

            <!-- Form Action Footer -->
            <div class="flex items-center justify-end gap-3 pb-12">
                <button type="button" class="px-5 py-3 rounded-xl bg-dark-700 hover:bg-dark-600 text-gray-300 text-xs font-medium border border-gray-700 transition-all" onclick="location.reload()">
                    <i class="bi bi-arrow-counterclockwise mr-1"></i> Reset Changes
                </button>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">
                    <i class="bi bi-save"></i> Save Settings
                </button>
            </div>
        </form>

        <!-- Danger Zone & Change Password -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Reset Database -->
            <div class="bg-dark-800/90 border border-red-900/50 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-red-500 to-orange-500"></div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-red-500/10 text-red-400 flex items-center justify-center border border-red-500/20">
                        <i class="bi bi-database-x text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-white">Reset Database</h2>
                        <p class="text-xs text-gray-400">Hapus semua data profil, proxy, playlist &amp; log</p>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mb-4">Tindakan ini tidak dapat dibatalkan. User &amp; settings tidak akan dihapus.</p>
                <button type="button" id="btn-reset-db" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-red-600 hover:bg-red-500 text-white font-medium text-sm transition-all">
                    <i class="bi bi-trash3"></i> Reset Database
                </button>
            </div>

            <!-- Change Password -->
            <div class="bg-dark-800/90 border border-gray-800 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 to-blue-500"></div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                        <i class="bi bi-key text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-white">Change Password</h2>
                        <p class="text-xs text-gray-400">Ganti password akun yang sedang login</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <input type="password" id="old_password" placeholder="Password Lama" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                    <input type="password" id="new_password" placeholder="Password Baru (min 6 karakter)" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                    <input type="password" id="confirm_password" placeholder="Konfirmasi Password Baru" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                    <button type="button" id="btn-change-password" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">
                        <i class="bi bi-shield-lock"></i> Change Password
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Application Script for Connection & Bot Testing -->
    <script>
        const baseUrl = '<?= base_url() ?>';
        document.addEventListener("DOMContentLoaded", function() {
            async function fetchWithTimeout(url, options = {}, timeoutMs = 10000) {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

                try {
                    return await fetch(url, {
                        ...options,
                        signal: controller.signal
                    });
                } catch (error) {
                    if (controller.signal.aborted) {
                        throw new Error("Request timed out after " + timeoutMs / 1000 + " seconds.");
                    }
                    throw error;
                } finally {
                    clearTimeout(timeoutId);
                }
            }

            const connectAllButton = document.getElementById("connect-all-settings");
            const connectAllResults = document.getElementById("connect-all-results");
            const connectAllChecks = document.getElementById("connect-all-checks");
            const connectAllTitle = document.getElementById("connect-all-title");
            const connectAllSummary = document.getElementById("connect-all-summary");
            const connectAllSpinner = document.getElementById("connect-all-spinner");
            const connectAllStorageKey = "run-all-settings-checks";

            function addConnectAllResult(label, message, success) {
                const item = document.createElement("li");
                item.className = "flex items-start gap-2 text-gray-300";
                const icon = document.createElement("i");
                icon.className = success ?
                    "bi bi-check-circle-fill text-emerald-400 mt-0.5" :
                    "bi bi-x-circle-fill text-rose-400 mt-0.5";
                const text = document.createElement("span");
                text.textContent = label + ": " + message;
                item.append(icon, text);
                connectAllChecks.appendChild(item);
                return success;
            }

            async function runConnectAllCheck(label, path, fields, timeoutMs = 12000, recordFailure = true) {
                try {
                    const response = await fetchWithTimeout(baseUrl + path, {
                        method: "POST",
                        body: new URLSearchParams(fields)
                    }, timeoutMs);
                    const data = await response.json();
                    if (!response.ok || data.success !== true) {
                        throw new Error(data.message || "Pemeriksaan gagal (HTTP " + response.status + ").");
                    }
                    return {
                        success: true,
                        data
                    };
                } catch (error) {
                    const message = error.message || "Pemeriksaan gagal.";
                    if (recordFailure) addConnectAllResult(label, message, false);
                    return {
                        success: false,
                        message
                    };
                }
            }

            async function runAllSettingsChecks() {
                connectAllResults.classList.remove("hidden");
                connectAllChecks.replaceChildren();
                connectAllTitle.textContent = "Memeriksa koneksi layanan...";
                connectAllSummary.textContent = "Pengaturan sudah disimpan. Hasil pemeriksaan akan muncul satu per satu.";
                connectAllSpinner.classList.remove("hidden");
                connectAllButton.disabled = true;
                connectAllButton.innerHTML = '<span class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Memeriksa...';
                let passed = 0;
                let required = 0;

                const apiCheck = await runConnectAllCheck("Dolphin API", "settings/check-connection", {
                    api_token: document.getElementById("api_token").value
                });
                required++;
                if (apiCheck.success) {
                    addConnectAllResult("Dolphin API", apiCheck.data.message || "Terhubung.", true);
                    passed++;
                }

                const localCheck = await runConnectAllCheck("Dolphin Local API", "settings/check-local", {
                    api_token: document.getElementById("api_token").value,
                    local_api_url: document.getElementById("local_api_url").value
                });
                required++;
                if (localCheck.success) {
                    addConnectAllResult("Dolphin Local API", localCheck.data.message || "Terhubung.", true);
                    passed++;
                }

                let pythonCheck = {
                    success: false,
                    message: "BayzBot tidak merespons."
                };
                for (let attempt = 0; attempt < 12; attempt++) {
                    pythonCheck = await runConnectAllCheck("BayzBot Python", "settings/check-python-health", {}, 20000, false);
                    if (pythonCheck.success && pythonCheck.data.running) break;
                    if (pythonCheck.success && pythonCheck.data.booting && attempt < 11) {
                        connectAllSummary.textContent = "Menunggu BayzBot selesai menyala...";
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        continue;
                    }
                    if (pythonCheck.success) {
                        pythonCheck = {
                            success: false,
                            message: pythonCheck.data.message || "BayzBot belum siap."
                        };
                    }
                    break;
                }
                required++;
                if (pythonCheck.success && pythonCheck.data.running) {
                    addConnectAllResult("BayzBot Python", pythonCheck.data.message || "Backend aktif.", true);
                    passed++;
                } else {
                    addConnectAllResult("BayzBot Python", pythonCheck.message || "Backend belum siap.", false);
                }

                required++;
                if (pythonCheck.success && pythonCheck.data.running) {
                    const bridgeCheck = await runConnectAllCheck("BayzBot Bridge", "settings/check-bot-bridge", {
                        bot_bridge_url: "",
                        bot_bridge_token: ""
                    }, 18000, false);
                    if (!bridgeCheck.success) {
                        addConnectAllResult("Ekstensi Dolphin", bridgeCheck.message || "Tidak bisa memeriksa ekstensi Dolphin.", false);
                    } else {
                        const profiles = Array.isArray(bridgeCheck.data.connected_profiles) ?
                            bridgeCheck.data.connected_profiles : [];
                        if (profiles.length > 0) {
                            addConnectAllResult("Ekstensi Dolphin", "Tersambung. Profil: " + profiles.join(", "), true);
                            passed++;
                        } else {
                            addConnectAllResult("Ekstensi Dolphin", "Belum ada profil Dolphin yang tersambung. Buka profil Dolphin dan periksa Options ekstensi.", false);
                        }
                    }
                } else {
                    addConnectAllResult("Ekstensi Dolphin", "Tidak dapat diperiksa karena backend BayzBot belum siap.", false);
                }

                const telegramEnabled = document.getElementById("telegram_notifications_enabled").checked ||
                    document.getElementById("telegram_ram_notifications_enabled").checked;
                if (telegramEnabled) {
                    required++;
                    const telegramCheck = await runConnectAllCheck("Telegram", "settings/test-telegram", {
                        telegram_bot_token: document.getElementById("telegram_bot_token").value,
                        telegram_chat_id: document.getElementById("telegram_chat_id").value
                    }, 15000);
                    if (telegramCheck.success) {
                        addConnectAllResult("Telegram", telegramCheck.data.message || "Pesan uji berhasil dikirim.", true);
                        passed++;
                    }
                } else {
                    addConnectAllResult("Telegram", "Dilewati karena notifikasi Telegram tidak diaktifkan.", true);
                }

                connectAllSpinner.classList.add("hidden");
                connectAllTitle.textContent = passed === required ? "Semua pemeriksaan berhasil" : "Pemeriksaan selesai, ada konfigurasi yang perlu diperbaiki";
                connectAllSummary.textContent = passed + " dari " + required + " pemeriksaan wajib berhasil. Periksa pesan di atas, perbaiki konfigurasi, lalu jalankan lagi.";
                connectAllButton.disabled = false;
                connectAllButton.innerHTML = '<i class="bi bi-plug"></i> Siapkan &amp; Hubungkan Semua';
                connectAllResults.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            }

            connectAllButton.addEventListener("click", function() {
                const form = document.getElementById("settings-form");
                if (!form.reportValidity()) return;
                const telegramEnabled = document.getElementById("telegram_notifications_enabled").checked ||
                    document.getElementById("telegram_ram_notifications_enabled").checked;
                const telegramNotice = telegramEnabled ?
                    " Karena notifikasi Telegram aktif, proses ini akan mengirim satu pesan uji ke Chat ID." :
                    "";
                if (!confirm("Simpan semua pengaturan lalu periksa koneksi Dolphin API, Local API, BayzBot, dan ekstensi?" + telegramNotice)) {
                    return;
                }
                sessionStorage.setItem(connectAllStorageKey, String(Date.now()));
                connectAllButton.disabled = true;
                connectAllButton.innerHTML = '<span class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Menyimpan...';
                form.requestSubmit();
            });

            // Cek koneksi (nyata): memanggil server, bukan simulasi
            const statusDiv = document.getElementById("connection-status");
            const statusText = document.getElementById("status-text");
            const spinner = document.getElementById("checking-spinner");
            const TONES = {
                amber: ["bg-amber-500/10", "border-amber-500/30", "text-amber-300"],
                green: ["bg-emerald-500/10", "border-emerald-500/30", "text-emerald-300"],
                red: ["bg-rose-500/10", "border-rose-500/30", "text-rose-300"],
            };

            function paintStatus(tone, text, spinning) {
                statusDiv.classList.remove("hidden", ...Object.values(TONES).flat());
                statusDiv.classList.add("flex", ...TONES[tone]);
                statusText.textContent = text;
                spinner.classList.toggle("hidden", !spinning);
            }

            async function runCheck(btn, path, fields) {
                const idleHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="w-3 h-3 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin mr-1 inline-block"></span> Checking...';
                paintStatus("amber", "Checking connection...", true);
                try {
                    const res = await fetch(baseUrl + path, {
                        method: "POST",
                        body: new URLSearchParams(fields)
                    });
                    const data = await res.json();
                    paintStatus(data.success ? "green" : "red", (data.success ? "" : "Failed: ") + data.message, false);
                } catch (error) {
                    paintStatus("red", "Error: " + error.message, false);
                }
                btn.disabled = false;
                btn.innerHTML = idleHtml;
            }

            document.getElementById("check-connection").addEventListener("click", function() {
                runCheck(this, "settings/check-connection", {
                    api_token: document.getElementById("api_token").value
                });
            });

            document.getElementById("check-local").addEventListener("click", function() {
                runCheck(this, "settings/check-local", {
                    api_token: document.getElementById("api_token").value,
                    local_api_url: document.getElementById("local_api_url").value
                });
            });

            document.getElementById("check-bot-bridge").addEventListener("click", async function() {
                const btn = this;
                const statusDiv = document.getElementById("bot-bridge-status");
                const statusText = document.getElementById("bot-bridge-status-text");
                btn.disabled = true;
                btn.innerHTML = '<span class="w-3 h-3 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin mr-1 inline-block"></span> Checking...';
                statusDiv.classList.remove("hidden", "bg-emerald-500/10", "border-emerald-500/30", "text-emerald-300", "bg-rose-500/10", "border-rose-500/30", "text-rose-300");
                statusDiv.classList.add("flex", "bg-amber-500/10", "border-amber-500/30", "text-amber-300");
                statusText.textContent = "Checking local Python bridge...";
                try {
                    const response = await fetchWithTimeout(baseUrl + "settings/check-bot-bridge", {
                        method: "POST",
                        body: new URLSearchParams({
                            bot_bridge_url: document.getElementById("bot_bridge_url").value,
                            bot_bridge_token: document.getElementById("bot_bridge_token").value
                        })
                    }, 15000);
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || "Bridge check failed.");
                    const profiles = Array.isArray(data.connected_profiles) ? data.connected_profiles : [];
                    statusDiv.classList.remove("bg-amber-500/10", "border-amber-500/30", "text-amber-300");
                    statusDiv.classList.add("bg-emerald-500/10", "border-emerald-500/30", "text-emerald-300");
                    statusText.textContent = "Bridge terhubung. Profil Dolphin terdaftar: " + (profiles.join(", ") || "belum ada ekstensi terhubung");
                } catch (error) {
                    statusDiv.classList.remove("bg-amber-500/10", "border-amber-500/30", "text-amber-300");
                    statusDiv.classList.add("bg-rose-500/10", "border-rose-500/30", "text-rose-300");
                    statusText.textContent = "Gagal: " + error.message;
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plug"></i> Test Python Bridge';
            });

            // Test Telegram Bot Button Handler
            document.getElementById("test-telegram-bot").addEventListener("click", async function() {
                const btn = this;
                const botToken = document.getElementById("telegram_bot_token").value;
                const chatId = document.getElementById("telegram_chat_id").value;
                const statusDiv = document.getElementById("telegram-status");
                const statusText = document.getElementById("telegram-status-text");
                const spinner = document.getElementById("telegram-spinner");

                if (!botToken || !chatId) {
                    statusDiv.classList.remove("hidden", "bg-emerald-500/10", "border-emerald-500/30", "text-emerald-300");
                    statusDiv.classList.add("flex", "bg-rose-500/10", "border-rose-500/30", "text-rose-300");
                    statusText.textContent = "Bot Token and Chat ID required!";
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="w-3 h-3 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin mr-1 inline-block"></span> Testing...';

                statusDiv.classList.remove("hidden", "bg-emerald-500/10", "border-emerald-500/30", "text-emerald-300", "bg-rose-500/10", "border-rose-500/30", "text-rose-300");
                statusDiv.classList.add("flex", "bg-amber-500/10", "border-amber-500/30", "text-amber-300");
                statusText.textContent = "Sending test message...";
                spinner.classList.remove("hidden");

                try {
                    const response = await fetch(`${baseUrl}settings/test-telegram`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `telegram_bot_token=${encodeURIComponent(botToken)}&telegram_chat_id=${encodeURIComponent(chatId)}`
                    });
                    const data = await response.json();

                    if (data.success) {
                        statusDiv.classList.remove("bg-amber-500/10", "border-amber-500/30", "text-amber-300");
                        statusDiv.classList.add("bg-emerald-500/10", "border-emerald-500/30", "text-emerald-300");
                        spinner.classList.add("hidden");
                        statusText.textContent = data.message;
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    statusDiv.classList.remove("bg-amber-500/10", "border-amber-500/30", "text-amber-300");
                    statusDiv.classList.add("bg-rose-500/10", "border-rose-500/30", "text-rose-300");
                    spinner.classList.add("hidden");
                    statusText.textContent = "Error: " + error.message;
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send"></i> Test Bot Connection';
            });

            // Python Health Monitoring Handlers
            document.getElementById("check-python-health").addEventListener("click", async function() {
                const btn = this;
                const statusDiv = document.getElementById("python-health-status");
                const statusText = document.getElementById("python-status-text");
                const statusMessage = document.getElementById("python-status-message");
                const statusIndicator = document.getElementById("python-status-indicator");

                btn.disabled = true;
                btn.innerHTML = '<span class="w-3 h-3 border-2 border-yellow-400 border-t-transparent rounded-full animate-spin mr-1 inline-block"></span> Checking...';
                statusDiv.classList.remove("hidden");
                statusIndicator.className = "w-3 h-3 rounded-full bg-yellow-500";
                statusText.textContent = "Checking Python health...";
                statusMessage.textContent = "";

                try {
                    const response = await fetchWithTimeout(baseUrl + "settings/check-python-health", {
                        method: "POST"
                    }, 15000);
                    const data = await response.json();
                    const statusCode = response.status;

                    if (statusCode === 202 && data.booting) {
                        // Python sedang dinyalakan
                        statusIndicator.className = "w-3 h-3 rounded-full bg-yellow-500";
                        statusText.textContent = "⏳ Python Booting";
                        statusMessage.textContent = data.message || "Python sedang dinyalakan di background...";
                    } else if (data.success && data.running) {
                        // Python sudah berjalan
                        statusIndicator.className = "w-3 h-3 rounded-full bg-emerald-500";
                        statusText.textContent = "✓ Python Running";
                        statusMessage.textContent = data.message + (data.uptime ? " - Uptime: " + data.uptime : "");
                    } else {
                        // Python tidak berjalan atau gagal
                        statusIndicator.className = "w-3 h-3 rounded-full bg-red-500";
                        statusText.textContent = "✗ Python Not Running";
                        statusMessage.textContent = data.message || "Python tidak aktif atau tidak merespons.";
                    }
                } catch (error) {
                    statusIndicator.className = "w-3 h-3 rounded-full bg-red-500";
                    statusText.textContent = "✗ Error";
                    statusMessage.textContent = "Error: " + error.message;
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-heart-pulse"></i> Check Health';
            });

            document.getElementById("stop-python-process").addEventListener("click", async function() {
                const btn = this;
                const statusDiv = document.getElementById("python-health-status");
                const statusText = document.getElementById("python-status-text");
                const statusMessage = document.getElementById("python-status-message");
                const statusIndicator = document.getElementById("python-status-indicator");

                if (!confirm("Hentikan semua proses BayzBot yang dijalankan dari aplikasi ini? Playback yang aktif akan terputus. Proses Python lain tidak akan dihentikan.")) {
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="w-3 h-3 border-2 border-red-400 border-t-transparent rounded-full animate-spin mr-1 inline-block"></span> Stopping...';
                statusDiv.classList.remove("hidden");
                statusIndicator.className = "w-3 h-3 rounded-full bg-yellow-500";
                statusText.textContent = "Menghentikan seluruh proses BayzBot...";
                statusMessage.textContent = "";

                try {
                    const response = await fetchWithTimeout(baseUrl + "settings/stop-python", {
                        method: "POST"
                    });
                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: data.message || 'Semua proses BayzBot telah dihentikan.',
                            background: '#111827',
                            color: '#fff',
                            confirmButtonColor: '#06b6d4',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                        statusIndicator.className = "w-3 h-3 rounded-full bg-orange-500";
                        statusText.textContent = "✓ Process Stopped";
                        statusMessage.textContent = data.message;
                    } else {
                        statusIndicator.className = "w-3 h-3 rounded-full bg-red-500";
                        statusText.textContent = "✗ Stop Failed";
                        statusMessage.textContent = data.message;
                    }
                } catch (error) {
                    statusIndicator.className = "w-3 h-3 rounded-full bg-red-500";
                    statusText.textContent = "✗ Error";
                    statusMessage.textContent = "Error: " + error.message;
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-stop-circle"></i> Stop All BayzBot Processes';
            });

            let pythonLogsLoading = false;
            let pythonLogsLoaded = false;

            async function loadPythonLogs() {
                if (pythonLogsLoading) return;
                pythonLogsLoading = true;
                const logsContainer = document.getElementById("python-logs-container");
                if (!pythonLogsLoaded) {
                    logsContainer.innerHTML = '<p class="text-gray-600">Loading logs...</p>';
                }

                try {
                    const response = await fetchWithTimeout(baseUrl + "settings/get-python-logs?lines=250", {}, 8000);
                    if (!response.ok) {
                        throw new Error("Server returned HTTP " + response.status + ".");
                    }
                    const data = await response.json();
                    pythonLogsLoaded = true;

                    if (data.success && Array.isArray(data.logs) && data.logs.length > 0) {
                        logsContainer.innerHTML = data.logs.map((line, i) =>
                            '<div key="' + i + '">' + escapeHtml(line) + '</div>'
                        ).join('');
                        logsContainer.scrollTop = logsContainer.scrollHeight;
                    } else if (data.success && Array.isArray(data.logs) && data.logs.length === 0) {
                        logsContainer.innerHTML = '<p class="text-gray-600">No logs available. Python may not have run yet.</p>';
                    } else {
                        logsContainer.innerHTML = '<p class="text-red-500">' + escapeHtml(data.message || "Invalid logs response.") + '</p>';
                    }
                } catch (error) {
                    logsContainer.innerHTML = '<p class="text-red-500">Error loading logs: ' + escapeHtml(error.message) + '</p>';
                } finally {
                    pythonLogsLoading = false;
                }
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            document.getElementById("refresh-python-logs").addEventListener("click", async function() {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise animate-spin"></i> Refreshing...';
                try {
                    await loadPythonLogs();
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh';
                }
            });

            document.getElementById("copy-python-logs").addEventListener("click", function() {
                const logsContainer = document.getElementById("python-logs-container");
                const text = logsContainer.innerText;
                navigator.clipboard.writeText(text).then(() => {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                    this.classList.replace("text-gray-300", "text-emerald-400");
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.replace("text-emerald-400", "text-gray-300");
                    }, 2000);
                });
            });

            document.getElementById("clear-python-logs-ui").addEventListener("click", function() {
                if (confirm("Bersihkan tampilan log saat ini? (File log di server tetap ada)")) {
                    document.getElementById("python-logs-container").innerHTML = '<p class="text-gray-600 italic">Logs cleared from view.</p>';
                    pythonLogsLoaded = false;
                }
            });

            const pendingConnectAllChecks = Number(sessionStorage.getItem(connectAllStorageKey) || 0);
            sessionStorage.removeItem(connectAllStorageKey);
            if (pendingConnectAllChecks && Date.now() - pendingConnectAllChecks < 120000) {
                void runAllSettingsChecks();
            }

            // Reset Database
            document.getElementById("btn-reset-db").addEventListener("click", function() {
                Swal.fire({
                    title: 'Reset Database?',
                    text: 'Semua data profil, proxy, playlist, stream & log akan dihapus permanen!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#374151',
                    confirmButtonText: 'Ya, Reset!',
                    background: '#111827',
                    color: '#fff'
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    const btn = document.getElementById("btn-reset-db");
                    btn.disabled = true;
                    btn.innerHTML = '<span class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span> Resetting...';
                    fetch(baseUrl + "settings/reset-database", {
                        method: "POST",
                        headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
                    })
                    .then(r => r.json())
                    .then(data => {
                        Swal.fire({ icon: data.success ? 'success' : 'error', title: data.success ? 'Berhasil' : 'Gagal', text: data.message, background: '#111827', color: '#fff', confirmButtonColor: data.success ? '#06b6d4' : '#ef4444' });
                    })
                    .catch(e => Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#111827', color: '#fff' }))
                    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-trash3"></i> Reset Database'; });
                });
            });

            // Change Password
            document.getElementById("btn-change-password").addEventListener("click", function() {
                const oldPw = document.getElementById("old_password").value;
                const newPw = document.getElementById("new_password").value;
                const confirmPw = document.getElementById("confirm_password").value;
                if (!oldPw || !newPw || !confirmPw) {
                    Swal.fire({ icon: 'warning', title: 'Lengkapi Form', text: 'Semua field password wajib diisi.', background: '#111827', color: '#fff', confirmButtonColor: '#06b6d4' });
                    return;
                }
                if (newPw !== confirmPw) {
                    Swal.fire({ icon: 'error', title: 'Tidak Cocok', text: 'Konfirmasi password tidak cocok.', background: '#111827', color: '#fff', confirmButtonColor: '#ef4444' });
                    return;
                }
                if (newPw.length < 6) {
                    Swal.fire({ icon: 'warning', title: 'Terlalu Pendek', text: 'Password baru minimal 6 karakter.', background: '#111827', color: '#fff', confirmButtonColor: '#06b6d4' });
                    return;
                }
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span> Processing...';
                fetch(baseUrl + "auth/change-password", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ old_password: oldPw, new_password: newPw })
                })
                .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
                .then(({ ok, data }) => {
                    Swal.fire({ icon: ok && data.success ? 'success' : 'error', title: ok && data.success ? 'Berhasil' : 'Gagal', text: data.message, background: '#111827', color: '#fff', confirmButtonColor: ok && data.success ? '#06b6d4' : '#ef4444' });
                    if (ok && data.success) {
                        document.getElementById("old_password").value = '';
                        document.getElementById("new_password").value = '';
                        document.getElementById("confirm_password").value = '';
                    }
                })
                .catch(e => Swal.fire({ icon: 'error', title: 'Error', text: e.message, background: '#111827', color: '#fff' }))
                .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-shield-lock"></i> Change Password'; });
            });

            // Load logs on page load
            loadPythonLogs();
            setInterval(() => void loadPythonLogs(), 10000);
        });
    </script>
</body>

</html>