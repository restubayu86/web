<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <script src="<?= base_url('assets/csrf.js') ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playlist - Dolphin{anty} Monitoring</title>
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
                    Playlist Manager
                    <span class="text-xs px-2.5 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-medium">v2.4 Active</span>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-medium text-sm shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all transform hover:-translate-y-0.5">
                    <i class="bi bi-plus-lg"></i> Create Playlist
                </button>
            </div>
        </header>

        <!-- Alert Container -->
        <div id="alertContainer" class="space-y-3 mb-6"></div>

        <!-- Redesigned Import Workflow Card -->
        <div class="bg-dark-800/90 border border-gray-800 rounded-2xl p-6 lg:p-8 shadow-xl backdrop-blur-md mb-10 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500"></div>

            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-800/80">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                        <i class="bi bi-cloud-upload text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-white">Import Songs to Playlist</h2>
                        <p class="text-xs text-gray-400">Add YouTube tracks via text file or batch URLs with automated validation</p>
                    </div>
                </div>
                <!-- Workflow Tabs Indicator -->
                <div class="hidden sm:flex items-center gap-2 text-xs text-gray-400 font-medium bg-dark-900 px-3 py-1.5 rounded-lg border border-gray-800">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    Workflow Wizard Ready
                </div>
            </div>

            <!-- Step 1: Select Target Playlist -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        <span class="text-cyan-400 font-bold mr-1">01.</span> Target Playlist Destination
                    </label>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <select id="playlistSelect" class="w-full bg-dark-900 border border-gray-700/80 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all appearance-none cursor-pointer">
                                <option value="">-- Select Playlist Destination --</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-400">
                                <i class="bi bi-chevron-down text-xs"></i>
                            </div>
                        </div>
                        <button onclick="openCreateModal()" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-dark-700 hover:bg-dark-600 text-cyan-400 font-medium text-sm border border-gray-700 transition-all">
                            <i class="bi bi-folder-plus"></i> New Playlist
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Import Sources (File or Textarea) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Upload File Box -->
                <div class="flex flex-col">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        <span class="text-cyan-400 font-bold mr-1">02A.</span> Bulk Upload (.TXT File)
                    </label>
                    <label for="playlistFile" class="flex flex-col items-center justify-center border-2 border-dashed border-gray-700 hover:border-cyan-500/60 rounded-xl p-6 bg-dark-900/50 hover:bg-dark-900 cursor-pointer transition-all group">
                        <div class="w-12 h-12 rounded-full bg-dark-700 text-cyan-400 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <i class="bi bi-file-earmark-arrow-up text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-200 mb-1" id="fileNameDisplay">Click to browse or drop .txt file</span>
                        <span class="text-xs text-gray-500">Supports newline separated YouTube links</span>
                        <input type="file" id="playlistFile" accept=".txt" class="hidden">
                    </label>
                </div>

                <!-- Paste URLs Box -->
                <div class="flex flex-col">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        <span class="text-cyan-400 font-bold mr-1">02B.</span> Paste YouTube URLs
                    </label>
                    <div class="relative flex-1 flex flex-col">
                        <textarea id="urlsTextarea" placeholder="https://www.youtube.com/watch?v=...&#10;https://youtu.be/..." class="w-full flex-1 bg-dark-900 border border-gray-700/80 rounded-xl p-4 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all resize-none min-h-[140px]"></textarea>
                    </div>
                </div>
            </div>

            <!-- Step 3: Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t border-gray-800/80">
                <button onclick="checkAndFetchYoutubeData()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-dark-700 hover:bg-dark-600 text-cyan-400 font-medium text-sm border border-cyan-500/30 transition-all shadow-sm">
                    <i class="bi bi-shield-check"></i> Check & Verify URLs
                </button>
                <button onclick="saveFetchedSongs()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm shadow-lg shadow-emerald-600/20 transition-all">
                    <i class="bi bi-plus-circle-fill"></i> Save to Playlist
                </button>
            </div>

            <!-- Loading Indicator Overlay -->
            <div id="loadingIndicator" class="hidden mt-6 p-6 rounded-xl bg-dark-900 border border-gray-800 text-center animate-pulse">
                <div class="inline-block w-8 h-8 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin mb-2"></div>
                <p class="text-xs font-medium text-gray-300">Processing URLs and querying YouTube metadata...</p>
            </div>

            <!-- Verification Results Display Container -->
            <div id="resultsContainer" class="hidden mt-6 bg-dark-900 border border-gray-800 rounded-xl p-5">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-800">
                    <div class="font-medium text-sm text-white flex items-center gap-2">
                        <i class="bi bi-check2-all text-cyan-400 text-base"></i> YouTube Metadata Verification
                    </div>
                    <div id="resultsStats" class="text-xs font-semibold px-2.5 py-1 rounded-md bg-dark-700 text-gray-300"></div>
                </div>
                <div id="resultsList" class="space-y-2 max-h-60 overflow-y-auto pr-1"></div>
            </div>
        </div>

        <!-- Playlists Grid Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    <i class="bi bi-collection-play text-cyan-400 text-lg"></i>
                    <h2 class="text-lg font-bold text-white tracking-wide">Your Playlists</h2>
                </div>
                <span id="playlistCountBadge" class="text-xs font-medium px-3 py-1 rounded-full bg-dark-800 text-gray-400 border border-gray-800">0 Total</span>
            </div>

            <div id="playlistsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Empty State Rendered Dynamically if No Playlists -->
                <div class="col-span-full py-16 text-center bg-dark-800/40 border border-dashed border-gray-800 rounded-2xl">
                    <div class="w-16 h-16 rounded-2xl bg-dark-800 text-gray-600 flex items-center justify-center mx-auto mb-4 text-2xl">
                        <i class="bi bi-collection-play"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-300 mb-1">No Playlists Found</h3>
                    <p class="text-xs text-gray-500 max-w-sm mx-auto mb-4">Create your first playlist using the button above to begin managing and monitoring YouTube streams.</p>
                    <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cyan-500/10 text-cyan-400 text-xs font-semibold border border-cyan-500/20 hover:bg-cyan-500/20 transition-all">
                        <i class="bi bi-plus"></i> Create First Playlist
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- Create Playlist Modal -->
    <div id="addPlaylistModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden p-4">
        <div class="bg-dark-800 border border-gray-700/80 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0 duration-200" id="addPlaylistModalContent">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700/60 bg-dark-900/50">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <i class="bi bi-folder-plus text-cyan-400"></i> Create New Playlist
                </h3>
                <button onclick="closeCreateModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-dark-700 transition-colors">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="addPlaylistForm">
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Playlist Name</label>
                        <input type="text" id="playlistName" required placeholder="e.g. Lofi Chill Streams 24/7" class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all">
                    </div>
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Description <span class="text-gray-500 lowercase">(optional)</span></label>
                        <textarea id="playlistDesc" rows="3" placeholder="Brief summary of this playlist..." class="w-full bg-dark-900 border border-gray-700 rounded-xl p-4 text-xs text-gray-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all resize-none"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 rounded-xl bg-dark-700 text-gray-300 text-xs font-medium hover:bg-dark-600 transition-colors">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-xs font-semibold shadow-lg shadow-cyan-500/20 hover:opacity-95 transition-all">Create Playlist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Playlist Detail Songs Modal -->
    <div id="playlistDetailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden p-4">
        <div class="bg-dark-800 border border-gray-700/80 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0 duration-200 max-h-[85vh] flex flex-col" id="playlistDetailModalContent">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700/60 bg-dark-900/50">
                <h3 class="text-base font-semibold text-white flex items-center gap-2" id="playlistDetailTitle">
                    <i class="bi bi-music-note-list text-cyan-400"></i> Playlist Songs
                </h3>
                <button onclick="closeDetailModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-dark-700 transition-colors">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                            <div class="mb-4 flex items-center">
                                <input type="text" id="songSearchInput" placeholder="Search songs..." class="w-full bg-dark-700 border border-gray-600 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-cyan-500" oninput="filterSongs()" />
                            </div>
                            <div id="songsList"></div>
                        </div>
            <div class="px-6 py-3 border-t border-gray-700/60 bg-dark-900/50 flex justify-between items-center">
                <div class="flex gap-2">
                    <button onclick="verifyAllSongs()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 text-xs font-medium transition-colors">
                        <i class="bi bi-shield-check"></i> Verify All
                    </button>
                    <button onclick="bulkDeleteSongs()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 text-xs font-medium transition-colors">
                        <i class="bi bi-trash"></i> Bulk Delete
                    </button>
                </div>
                <button onclick="closeDetailModal()" class="px-4 py-2 rounded-xl bg-dark-700 text-gray-300 text-xs font-medium hover:bg-dark-600 transition-colors">Close</button>
            </div>
        </div>
    </div>

    <!-- Application Logic Script -->
    <script>
        const baseUrl = '<?= base_url() ?>';
        let currentPlaylistId = null;
        let fetchedSongs = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadPlaylists();
            loadPlaylistOptions();
            setupEventListeners();
        });

        function setupEventListeners() {
            const fileInput = document.getElementById('playlistFile');
            fileInput.addEventListener('change', function() {
                if (this.files[0]) {
                    document.getElementById('fileNameDisplay').textContent = this.files[0].name;
                    uploadPlaylistFile();
                }
            });

            document.getElementById('addPlaylistForm').addEventListener('submit', function(e) {
                e.preventDefault();
                createPlaylist();
            });
        }

        // Modal Open/Close Controls with Smooth Transition
        function openCreateModal() {
            const modal = document.getElementById('addPlaylistModal');
            const content = document.getElementById('addPlaylistModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeCreateModal() {
            const modal = document.getElementById('addPlaylistModal');
            const content = document.getElementById('addPlaylistModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                document.getElementById('addPlaylistForm').reset();
            }, 200);
        }

        function openDetailModal() {
            const modal = document.getElementById('playlistDetailModal');
            const content = document.getElementById('playlistDetailModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeDetailModal() {
            const modal = document.getElementById('playlistDetailModal');
            const content = document.getElementById('playlistDetailModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        function loadPlaylistOptions() {
            fetch(`${baseUrl}api/playlist/all`)
                .then(res => res.json())
                .then(data => {
                    const select = document.getElementById('playlistSelect');
                    select.innerHTML = '<option value="">-- Select Playlist Destination --</option>';
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(playlist => {
                            const option = document.createElement('option');
                            option.value = playlist.id;
                            option.textContent = playlist.playlist_name;
                            select.appendChild(option);
                        });
                        select.value = data.data[0].id;
                        currentPlaylistId = data.data[0].id;
                    }
                }).catch(err => console.error('Failed to load playlist options:', err));
        }

        function loadPlaylists() {
            fetch(`${baseUrl}api/playlist/all`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderPlaylists(data.data);
                    }
                })
                .catch(err => showAlert('Error loading playlists', 'danger'));
        }

        function renderPlaylists(playlists) {
            const container = document.getElementById('playlistsContainer');
            const badge = document.getElementById('playlistCountBadge');
            badge.textContent = `${playlists.length} Total`;

            if (playlists.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full py-16 text-center bg-dark-800/40 border border-dashed border-gray-800 rounded-2xl">
                        <div class="w-16 h-16 rounded-2xl bg-dark-800 text-gray-600 flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="bi bi-collection-play"></i>
                        </div>
                        <h3 class="text-base font-semibold text-gray-300 mb-1">No Playlists Found</h3>
                        <p class="text-xs text-gray-500 max-w-sm mx-auto mb-4">Create your first playlist using the button above to begin managing and monitoring YouTube streams.</p>
                        <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cyan-500/10 text-cyan-400 text-xs font-semibold border border-cyan-500/20 hover:bg-cyan-500/20 transition-all">
                            <i class="bi bi-plus"></i> Create First Playlist
                        </button>
                    </div>
                `;
                return;
            }

            let html = '';
            playlists.forEach(playlist => {
                html += `
                    <div class="bg-dark-800 border border-gray-800 hover:border-cyan-500/50 rounded-2xl p-6 transition-all duration-300 hover:shadow-xl hover:shadow-cyan-500/5 flex flex-col justify-between group cursor-pointer relative overflow-hidden" onclick="viewPlaylistDetail(${playlist.id})">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-cyan-500/5 rounded-full blur-2xl group-hover:bg-cyan-500/10 transition-colors pointer-events-none"></div>
                        <div>
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <h3 class="text-base font-bold text-white group-hover:text-cyan-400 transition-colors">${playlist.playlist_name}</h3>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-dark-900 text-cyan-400 text-xs font-medium border border-gray-800">
                                    <i class="bi bi-music-note"></i> ${playlist.total_songs || 0}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 line-clamp-2 mb-4 leading-relaxed">${playlist.description || 'No description provided for this monitoring playlist.'}</p>
                        </div>
                        <div class="pt-4 border-t border-gray-800/80 flex items-center justify-between">
                            <span class="text-[11px] text-gray-500 flex items-center gap-1">
                                <i class="bi bi-clock"></i> Active Stream
                            </span>
                            <div class="flex items-center gap-2">
                                <button class="px-3 py-1.5 rounded-lg bg-dark-900 hover:bg-dark-700 text-gray-300 hover:text-white text-xs font-medium border border-gray-700/60 transition-colors" onclick="event.stopPropagation(); viewPlaylistDetail(${playlist.id})">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-medium border border-red-500/20 transition-colors" onclick="deletePlaylist(event, ${playlist.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function createPlaylist() {
            const name = document.getElementById('playlistName').value;
            const desc = document.getElementById('playlistDesc').value;

            const formData = new FormData();
            formData.append('playlist_name', name);
            formData.append('description', desc);

            fetch(`${baseUrl}api/playlist/create`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Playlist created successfully!', 'success');
                        closeCreateModal();
                        loadPlaylists();
                        loadPlaylistOptions();
                    } else {
                        showAlert(data.message || 'Failed to create playlist', 'danger');
                    }
                }).catch(err => showAlert('Error submitting playlist', 'danger'));
        }

        function uploadPlaylistFile() {
            const fileInput = document.getElementById('playlistFile');
            const file = fileInput.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('playlist_file', file);

            document.getElementById('loadingIndicator').classList.remove('hidden');

            fetch(`${baseUrl}api/playlist/upload-file`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('loadingIndicator').classList.add('hidden');
                    if (data.success) {
                        document.getElementById('urlsTextarea').value = data.urls.join('\n');
                        showAlert(`Successfully loaded ${data.count} URLs from file`, 'success');
                    } else {
                        showAlert(data.message || 'Failed to parse file', 'danger');
                    }
                }).catch(err => {
                    document.getElementById('loadingIndicator').classList.add('hidden');
                    showAlert('Error uploading file', 'danger');
                });
        }

        function checkAndFetchYoutubeData() {
            const playlistId = document.getElementById('playlistSelect').value;
            if (!playlistId) {
                showAlert('Please select a target playlist destination first', 'warning');
                return;
            }
            currentPlaylistId = playlistId;

            const urls = document.getElementById('urlsTextarea').value.trim().split('\n').filter(u => u.trim());
            if (urls.length === 0) {
                showAlert('Please enter or upload YouTube URLs to verify', 'warning');
                return;
            }

            document.getElementById('loadingIndicator').classList.remove('hidden');

            const formData = new FormData();
            urls.forEach((url, index) => {
                formData.append(`urls[${index}]`, url);
            });

            fetch(`${baseUrl}api/playlist/check-urls`, {
                    method: 'POST',
                    body: formData
                })
                .then(async res => {
                    let data;
                    try {
                        data = await res.json();
                    } catch (error) {
                        throw new Error(`Server returned an invalid response (HTTP ${res.status})`);
                    }
                    if (!res.ok) {
                        throw new Error(data.message || `URL verification failed (HTTP ${res.status})`);
                    }
                    return data;
                })
                .then(data => {
                    document.getElementById('loadingIndicator').classList.add('hidden');
                    if (data.success) {
                        fetchedSongs = data.results;
                        displayResults(data);
                    } else {
                        showAlert(data.message || 'Verification failed', 'danger');
                    }
                }).catch(err => {
                    document.getElementById('loadingIndicator').classList.add('hidden');
                    showAlert(err.message || 'Error verifying URLs', 'danger');
                });
        }

        function escapeHtml(value) {
            const element = document.createElement('span');
            element.textContent = value ?? '';
            return element.innerHTML;
        }

        function displayResults(data) {
            const container = document.getElementById('resultsContainer');
            const list = document.getElementById('resultsList');
            const stats = document.getElementById('resultsStats');

            stats.innerHTML = `<span class="text-emerald-400">✓ Valid: ${data.valid_count}</span> | <span class="text-rose-400">✗ Errors: ${data.error_count}</span>`;

            let html = '<div class="space-y-2">';
            
            data.results.forEach(result => {
                html += `
<div class="bg-dark-700 p-2 rounded">
    <div class="font-semibold text-white text-sm">${escapeHtml(result.song_title)}</div>
    <div class="text-gray-400 text-xs mt-0.5">${escapeHtml(result.artist_name)} • ${escapeHtml(result.album || 'YouTube')}</div>
</div>
                `;
            });

            if (data.errors && data.errors.length > 0) {
                data.errors.forEach(error => {
                    html += `<div class="bg-rose-500/10 p-2 rounded text-rose-400 text-xs">❌ ${escapeHtml(error.url)}: ${escapeHtml(error.error || 'Could not fetch video data')}</div>`;
                });
            }

            html += '</div>';
            list.innerHTML = html;
            container.classList.remove('hidden');
        }

        function saveFetchedSongs() {
            if (!currentPlaylistId) {
                showAlert('Please select a target playlist', 'warning');
                return;
            }

            if (fetchedSongs.length === 0) {
                showAlert('No songs available to save. Please verify URLs first.', 'warning');
                return;
            }

            document.getElementById('loadingIndicator').classList.remove('hidden');

            const formData = new FormData();
            formData.append('playlist_id', currentPlaylistId);
            fetchedSongs.forEach((song, index) => {
                formData.append(`songs[${index}][video_id]`, song.video_id);
                formData.append(`songs[${index}][song_title]`, song.song_title);
                formData.append(`songs[${index}][artist_name]`, song.artist_name);
                formData.append(`songs[${index}][album]`, song.album || '');
                formData.append(`songs[${index}][duration]`, song.duration || 0);
            });

            fetch(`${baseUrl}api/playlist/save-bulk`, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('loadingIndicator').classList.add('hidden');
                    if (data.success) {
                        showAlert(`Successfully saved ${data.saved} songs (${data.duplicates_count || 0} duplicates skipped)`, 'success');
                        document.getElementById('urlsTextarea').value = '';
                        document.getElementById('playlistFile').value = '';
                        document.getElementById('fileNameDisplay').textContent = 'Click to browse or drop .txt file';
                        document.getElementById('resultsContainer').classList.add('hidden');
                        fetchedSongs = [];
                        loadPlaylists();
                    } else {
                        showAlert(data.message || 'Failed to save songs', 'danger');
                    }
                }).catch(err => {
                    document.getElementById('loadingIndicator').classList.add('hidden');
                    showAlert('Error saving songs', 'danger');
                });
        }

        function viewPlaylistDetail(id) {
            fetch(`${baseUrl}api/playlist/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const playlist = data.data;
                        document.getElementById('playlistDetailTitle').innerHTML = `<i class="bi bi-music-note-list text-cyan-400"></i> ${playlist.playlist_name} <span class="text-xs px-2 py-0.5 rounded bg-dark-700 text-cyan-400 ml-2">${playlist.songs ? playlist.songs.length : 0} songs</span>`;
                        renderSongs(playlist.songs || []);
                        openDetailModal();
                    } else {
                        showAlert('Failed to load playlist details', 'danger');
                    }
                }).catch(err => showAlert('Error fetching playlist details', 'danger'));
        }

        let songsData = [];
        let sortConfig = { key: 'no', direction: 'asc' };

        function filterSongs() {
            const searchTerm = document.getElementById('songSearchInput').value.toLowerCase();
            let filtered = songsData;

            if (searchTerm) {
                filtered = songsData.filter(song => 
                    song.song_title.toLowerCase().includes(searchTerm) ||
                    song.artist_name.toLowerCase().includes(searchTerm) ||
                    (song.album && song.album.toLowerCase().includes(searchTerm))
                );
            }

            renderSongsTable(filtered);
        }

        function sortSongs(column) {
            if (sortConfig.key === column) {
                sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
            } else {
                sortConfig.key = column;
                sortConfig.direction = 'asc';
            }

            let sorted = [...songsData];
            sorted.sort((a, b) => {
                let aVal = a[column] || '';
                let bVal = b[column] || '';
                
                if (column === 'play_count') {
                    aVal = parseInt(aVal) || 0;
                    bVal = parseInt(bVal) || 0;
                }

                if (aVal < bVal) return sortConfig.direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return sortConfig.direction === 'asc' ? 1 : -1;
                return 0;
            });

            renderSongsTable(sorted);
        }

        function renderSongs(songs) {
            const container = document.getElementById('songsList');
            if (!songs || songs.length === 0) {
                container.innerHTML = `
                    <div class="py-12 text-center text-gray-500 text-xs">
                        <i class="bi bi-music-note text-3xl mb-2 block opacity-40"></i>
                        No songs found in this playlist.
                    </div>
                `;
                return;
            }

            songsData = songs.map((song, index) => ({
                ...song,
                no: index + 1
            }));

            renderSongsTable(songsData);
        }

        function renderSongsTable(songs) {
            const container = document.getElementById('songsList');
            let html = `
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-700/60 text-gray-400 font-semibold bg-dark-900/40">
                                                                                        <th class="p-3 w-8"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded accent-cyan-500"></th>
                                                                                        <th class="p-3 w-12 cursor-pointer hover:text-cyan-400" onclick="sortSongs('no')">No</th>
                                                                                        <th class="p-3 cursor-pointer hover:text-cyan-400" onclick="sortSongs('song_title')">Title</th>
                                                                                        <th class="p-3 cursor-pointer hover:text-cyan-400" onclick="sortSongs('artist_name')">Artist</th>
                                                                                        <th class="p-3 cursor-pointer hover:text-cyan-400" onclick="sortSongs('album')">Album</th>
                                                                                        <th class="p-3 text-center w-16 cursor-pointer hover:text-cyan-400" onclick="sortSongs('play_count')">Plays</th>
                                                                                        <th class="p-3 text-center w-24">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-800">
                        `;

                        songs.forEach((song) => {
                            html += `
                                <tr class="hover:bg-dark-700/30 transition-colors">
                                    <td class="p-3"><input type="checkbox" class="song-checkbox w-4 h-4 rounded accent-cyan-500" value="${song.id}" onchange="updateSelectedSongs()"></td>
                                    <td class="p-3 text-gray-500 font-medium">${song.no}</td>
                                    <td class="p-3 font-medium text-white truncate max-w-sm">${song.song_title}</td>
                                    <td class="p-3 text-gray-300 truncate max-w-xs">${song.artist_name || '-'}</td>
                                    <td class="p-3 text-gray-400 truncate max-w-xs">${song.album || '-'}</td>
                                    <td class="p-3 text-center text-gray-400">${song.play_count || 0}</td>
                                    <td class="p-3 text-center">
                                        <div class="flex gap-1 justify-center">
                                                                                        <button onclick="copySongUrl('${song.video_id}')" class="p-1.5 rounded-lg bg-dark-700 hover:bg-blue-500/20 text-gray-400 hover:text-blue-400 transition-colors" title="Copy URL">
                                                                                            <i class="bi bi-link-45deg text-xs"></i>
                                                                                        </button>
                                                                                        <button onclick="deleteSong(${song.id})" class="p-1.5 rounded-lg bg-dark-700 hover:bg-rose-500/20 text-gray-400 hover:text-rose-400 transition-colors" title="Delete">
                                                                                            <i class="bi bi-trash text-xs"></i>
                                                                                        </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });

            html += `</tbody></table></div>`;
            container.innerHTML = html;
            selectedSongs = [];
        }

        function deleteSong(songId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'Remove this song from playlist?',
                icon: 'warning',
                background: '#111827',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#374151',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`${baseUrl}api/playlist/song/remove/${songId}`, {
                        method: 'DELETE'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showAlert(data.message || 'Song removed successfully', 'success');
                            // Refresh modal content
                            viewPlaylistDetail(currentPlaylistId);
                            // Refresh playlist cards
                            loadPlaylists();
                        } else {
                            showAlert(data.message || 'Failed to remove song', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Delete error:', err);
                        showAlert('Error removing song', 'error');
                    });
                }
            });
        }

        function copySongUrl(videoId) {
            const url = `https://www.youtube.com/watch?v=${videoId}`;
            navigator.clipboard.writeText(url).then(() => {
                showAlert('YouTube URL copied to clipboard!', 'success');
            }).catch(err => {
                showAlert('Failed to copy URL', 'error');
            });
        }

        function refreshSong(songId) {
            showAlert('Refreshing song data...', 'info');
            fetch(`${baseUrl}api/playlist/song/refresh/${songId}`, {
                method: 'POST'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('Song refreshed successfully', 'success');
                    if (currentPlaylistId) {
                        viewPlaylistDetail(currentPlaylistId);
                    }
                } else {
                    showAlert(data.message || 'Failed to refresh song', 'error');
                }
            })
            .catch(err => showAlert('Error refreshing song', 'error'));
        }

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.song-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedSongs();
        }

        function updateSelectedSongs() {
            const checkboxes = document.querySelectorAll('.song-checkbox:checked');
            selectedSongs = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const totalCheckboxes = document.querySelectorAll('.song-checkbox').length;
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = selectedSongs.length === totalCheckboxes && totalCheckboxes > 0;
            }
        }

        function verifyAllSongs() {
            if (selectedSongs.length === 0) {
                showAlert('Please select songs to verify', 'warning');
                return;
            }

            showAlert(`Verifying ${selectedSongs.length} song(s)...`, 'info');
            document.getElementById('loadingIndicator').classList.remove('hidden');

            let completed = 0;
            let failed = 0;

            selectedSongs.forEach(songId => {
                fetch(`${baseUrl}api/playlist/song/refresh/${songId}`, {
                    method: 'POST'
                })
                .then(res => res.json())
                .then(data => {
                    completed++;
                    if (!data.success) failed++;
                    
                    if (completed === selectedSongs.length) {
                        document.getElementById('loadingIndicator').classList.add('hidden');
                        showAlert(`Verified ${completed - failed}/${completed} songs successfully`, failed > 0 ? 'warning' : 'success');
                        if (currentPlaylistId) {
                            viewPlaylistDetail(currentPlaylistId);
                        }
                    }
                })
                .catch(err => {
                    completed++;
                    failed++;
                    if (completed === selectedSongs.length) {
                        document.getElementById('loadingIndicator').classList.add('hidden');
                        showAlert(`Verified ${completed - failed}/${completed} songs`, 'warning');
                        if (currentPlaylistId) {
                            viewPlaylistDetail(currentPlaylistId);
                        }
                    }
                });
            });
        }

        function bulkDeleteSongs() {
            if (selectedSongs.length === 0) {
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
                            icon: 'warning',
                            title: 'No Songs Selected',
                            background: '#111827',
                            color: '#fff'
                        });
                        return;
                    }

            Swal.fire({
                title: 'Delete Selected Songs?',
                text: `Remove ${selectedSongs.length} song(s) from playlist?`,
                icon: 'warning',
                background: '#111827',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#374151',
                confirmButtonText: 'Yes, delete all!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('loadingIndicator').classList.remove('hidden');
                    
                    const deletePromises = selectedSongs.map(songId =>
                        fetch(`${baseUrl}api/playlist/song/remove/${songId}`, {
                            method: 'DELETE'
                        }).then(res => res.json())
                    );

                    Promise.all(deletePromises)
                        .then(results => {
                            document.getElementById('loadingIndicator').classList.add('hidden');
                            const successful = results.filter(r => r.success).length;
                            showAlert(`Deleted ${successful}/${selectedSongs.length} songs`, successful === selectedSongs.length ? 'success' : 'warning');
                            if (currentPlaylistId) {
                                viewPlaylistDetail(currentPlaylistId);
                            }
                            loadPlaylists();
                        })
                        .catch(err => {
                            document.getElementById('loadingIndicator').classList.add('hidden');
                            showAlert('Error deleting songs', 'error');
                        });
                }
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
    </script>
</body>

</html>