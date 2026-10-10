<!DOCTYPE html>
<html lang="id" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutorial - Dolphin{anty} Monitoring</title>
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
    <style>
        body { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #111827; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
    </style>
</head>

<body class="bg-dark-900 text-gray-100 min-h-screen flex antialiased selection:bg-brand-500 selection:text-black">
    <?= view('partials/sidebar_tailwind') ?>

    <main class="flex-1 ml-20 lg:ml-24 p-6 lg:p-10">
        <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-1">
                    <i class="bi bi-book"></i> Panduan penggunaan
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-white">Tutorial Aplikasi</h1>
                <p class="text-sm text-gray-400 mt-2">Langkah awal untuk menyiapkan Dolphin{anty}, BayzBot, playlist, dan stream.</p>
            </div>
            <a href="<?= site_url('settings') ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-dark-800 border border-gray-700 text-gray-300 text-sm hover:border-cyan-500/40 hover:text-cyan-300 transition-colors">
                <i class="bi bi-gear"></i> Kembali ke Settings
            </a>
        </header>

        <div class="max-w-5xl space-y-6">
            <section class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-5 sm:p-6">
                <h2 class="font-semibold text-white flex items-center gap-2"><i class="bi bi-list-check text-cyan-400"></i> Urutan penggunaan</h2>
                <p class="text-sm text-gray-300 mt-2">Pastikan Dolphin{anty} dan profil browser siap, hubungkan ekstensi BayzBot, buat playlist dan stream, kemudian mulai playback dari halaman Stream.</p>
            </section>

            <section class="rounded-2xl border border-gray-800 bg-dark-800/90 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white"><span class="text-cyan-400 mr-2">1.</span>Konfigurasi Settings</h2>
                <ol class="mt-4 space-y-3 text-sm text-gray-300 list-decimal list-inside">
                    <li>Masukkan <strong class="text-white">Dolphin API Token</strong> dan klik <strong class="text-white">Check Connection</strong>.</li>
                    <li>Pastikan <strong class="text-white">Local API URL</strong> sesuai dengan port di aplikasi Dolphin{anty} (umumnya <code class="text-cyan-300">http://localhost:3001</code>), lalu klik <strong class="text-white">Check Local API</strong>.</li>
                    <li>URL BayzBot biasanya <code class="text-cyan-300">http://127.0.0.1:8766</code>. Simpan pengaturan setelah perubahan.</li>
                    <li>Jika memakai Telegram, isi Bot Token dan Target Chat ID. Uji melalui tombol <strong class="text-white">Test Bot Connection</strong>.</li>
                </ol>
            </section>

            <section class="rounded-2xl border border-gray-800 bg-dark-800/90 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white"><span class="text-cyan-400 mr-2">2.</span>Hubungkan ekstensi BayzBot</h2>
                <ol class="mt-4 space-y-3 text-sm text-gray-300 list-decimal list-inside">
                    <li>Di Chrome, buka <code class="text-cyan-300">chrome://extensions</code> dan aktifkan <strong class="text-white">Developer mode</strong>.</li>
                    <li>Pilih <strong class="text-white">Load unpacked</strong>, lalu pilih folder <code class="text-cyan-300">bot-extension</code> di dalam folder proyek.</li>
                    <li>Buka Options ekstensi. Gunakan WebSocket URL <code class="text-cyan-300">ws://localhost:8765</code> dan isi Dolphin Browser Profile ID yang sesuai.</li>
                    <li>Simpan pengaturan ekstensi, lalu buka profil Dolphin tersebut agar ekstensi tersambung.</li>
                    <li>Kembali ke aplikasi dan klik <strong class="text-white">Check Health</strong>. Tunggu hingga status BayzBot menunjukkan Running.</li>
                </ol>
                <p class="mt-4 text-xs text-gray-500">Backend BayzBot juga dapat dinyalakan otomatis saat playback dimulai. Log proses tersedia di panel Python Process Monitor pada Settings.</p>
            </section>

            <section class="rounded-2xl border border-gray-800 bg-dark-800/90 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white"><span class="text-cyan-400 mr-2">3.</span>Buat playlist dan stream</h2>
                <ol class="mt-4 space-y-3 text-sm text-gray-300 list-decimal list-inside">
                    <li>Buka menu <strong class="text-white">Playlist</strong>, buat playlist, lalu tambahkan lagu YouTube Music.</li>
                    <li>Pastikan lagu memiliki judul dan artis. Metadata ini dipakai sebagai informasi cadangan pada panel Now Playing.</li>
                    <li>Buka menu <strong class="text-white">Stream</strong> dan pilih <strong class="text-white">Create Stream</strong>.</li>
                    <li>Isi nama stream, pilih profil Dolphin dan playlist, lalu buat stream.</li>
                    <li>Klik <strong class="text-white">Play</strong>. Kartu stream menampilkan status, Now Playing, total pemutaran, durasi, dan waktu mulai.</li>
                </ol>
            </section>

            <section class="rounded-2xl border border-gray-800 bg-dark-800/90 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white"><span class="text-cyan-400 mr-2">4.</span>Monitoring RAM</h2>
                <ul class="mt-4 space-y-3 text-sm text-gray-300 list-disc list-inside">
                    <li>Atur <strong class="text-white">Threshold RAM (%)</strong> pada Settings (default 85%).</li>
                    <li>Aktifkan notifikasi RAM tinggi jika ingin menerima peringatan Telegram.</li>
                    <li>Aktifkan <strong class="text-white">Auto-stop streams</strong> jika stream perlu dihentikan saat RAM melewati threshold.</li>
                    <li>Lihat persentase dan penggunaan RAM terkini pada panel RAM Monitor di halaman Stream.</li>
                </ul>
            </section>

            <section class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5 sm:p-6">
                <h2 class="font-semibold text-amber-200 flex items-center gap-2"><i class="bi bi-exclamation-triangle"></i> Menghentikan BayzBot</h2>
                <p class="text-sm text-gray-300 mt-2">Gunakan tombol <strong class="text-white">Stop All BayzBot Processes</strong> pada Settings untuk menghentikan proses BayzBot dan proses turunannya. Playback aktif akan terputus; proses Python lain tidak ditarget.</p>
                <p class="text-sm text-gray-300 mt-2">Alternatif dari Windows: jalankan <code class="text-amber-200">stop_all_bayzbot.bat</code> di folder proyek. Untuk melihat PID yang akan ditarget tanpa menghentikannya, gunakan argumen <code class="text-amber-200">--dry-run</code>. Server web dihentikan terpisah dengan <kbd class="rounded border border-gray-600 px-1.5 py-0.5 text-xs">Ctrl+C</kbd> di terminal server.</p>
            </section>

            <section class="rounded-2xl border border-gray-800 bg-dark-800/90 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white">Pemecahan masalah singkat</h2>
                <ul class="mt-4 space-y-3 text-sm text-gray-300 list-disc list-inside">
                    <li><strong class="text-white">Web tidak terbuka:</strong> pastikan server PHP aktif dan buka alamat yang sama dengan konfigurasi baseURL, biasanya <code class="text-cyan-300">http://127.0.0.1:8080</code>.</li>
                    <li><strong class="text-white">BayzBot tidak tersambung:</strong> periksa status Python, alamat WebSocket, ID profil di ekstensi, dan log di Settings.</li>
                    <li><strong class="text-white">Profil tidak bisa dijalankan:</strong> pastikan Dolphin{anty} aktif dan Local API URL/port benar.</li>
                    <li><strong class="text-white">Judul atau artis kosong:</strong> pastikan metadata tersedia pada daftar lagu playlist, lalu mulai ulang playback.</li>
                </ul>
            </section>

            <footer class="pb-4 text-xs text-gray-500">Untuk instalasi baru atau perintah terminal lengkap, lihat README.md di folder proyek.</footer>
        </div>
    </main>
</body>

</html>
