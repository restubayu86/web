# Catatan Perubahan (perapihan)

## Keamanan
- `.env` dikosongkan dari API key; ditambah `.env.example`. **Rotasi API key lama.**
- Dihapus: `public/phpinfo.php`, `writable/database.sqlite`, log, dan session dari arsip.
- `AdminSeeder`: tidak ada password bawaan lagi (dari `.env` atau acak), tidak membuat user ganda.
- CSRF aktif global + `public/assets/csrf.js` + `csrf_field()` di form login dan settings.
- `SetupFilter`: `/setup/*` dan `/login/checkSetup` hanya terbuka saat instalasi baru atau setelah login.
- Start/stop profil Dolphin diubah dari GET ke POST.
- `client_error()`: pesan exception tidak lagi dikirim mentah ke browser di production (18 titik).

## Bug yang diperbaiki
- **Halaman login macet di "Error checking setup. Please refresh the page."** Regresi dari `SetupFilter`: halaman login
  memanggil `login/checkSetup` di setiap muat, padahal endpoint itu kini dikunci setelah admin ada. Sekarang pengecekan
  setup hanya berjalan pada instalasi baru (`needs_setup`), form login langsung tampil di kasus normal, respons non-JSON
  ditangani dengan pesan HTTP yang jelas, dan ada petunjuk `php spark db:seed AdminSeeder` jika belum ada admin.
- Setelah login user mendarat di 404 (`Home` mengalihkan ke `/index.php/profile`); kini diarahkan ke Stream (menu teratas). Dashboard Browser dipindah ke `/browser`.
- Dropdown profil di halaman Stream kosong (memanggil `api/profile/all` yang tidak ada).
- Migrasi `AddDolphinIdToProxies` salah folder dan memakai sintaks MySQL; kini di folder yang benar,
  kompatibel SQLite, dan idempoten. Versinya dipertahankan agar tidak berjalan ulang di DB lama.
- Rute `api/playlist/song/play/(:num)` menunjuk ke method yang tidak ada; dihapus.
- Rute duplikat dirapikan dan dikelompokkan (`setup`, `auth`).
- `start_server.bat` tidak lagi memuat path pribadi; file `php` (sisa output `ps`) dihapus.

## Menu dinamis
- Sidebar di 8 view (hardcode, masing-masing berbeda) diganti satu sumber: `app/Config/Menu.php`.
- Urutan: Stream, Browser, Proxies, Playlist, Users, Log, Settings.
- `menu_helper` menentukan menu aktif dari URL (sebelumnya `current_url() == base_url(...)`, gagal jika ada query atau slash akhir).
- Dua partial menjaga tampilan lama: `sidebar_tailwind` (proxies, playlist, settings) dan `sidebar_classic` (stream, dashboard, log, users, layout).
- Ikon tidak valid `bi-play-list` di `layout.php` ikut terkoreksi.

## Halaman Browser (`/browser`)
- `dashboard.php` ditulis ulang dengan gaya Tailwind yang sama seperti Proxies/Playlist: header, kartu statistik (Total/Running/Stopped), tab filter, tabel, alert, bulk bar, dan pager.
- Fitur baru: pencarian (diteruskan ke Dolphin), filter status, pilih banyak lalu start/stop massal (berurutan, jeda 400 ms), paginasi, salin ID profil, auto-refresh 15 detik (berhenti saat tab tidak aktif).
- Perbaikan: nama profil tidak lagi lewat `innerHTML` mentah (XSS), `tags` kosong tidak membuat halaman error, kolom pencarian yang tadinya dekoratif kini berfungsi.
- `Monitoring::getProfiles` meneruskan `query`, `page`, `limit` (maks 100) ke Dolphin.
- Tombol logout (satu-satunya di aplikasi) dipertahankan di header halaman ini.
- Bentuk data profil dari Dolphin dikumpulkan di satu fungsi, `normalizeProfile()`, supaya mudah disesuaikan.

## Start/Stop lewat Local API
- Sebelumnya start/stop memanggil `anty-api.com` (API remote). Kini memakai Local API resmi Dolphin{anty}
  lewat `app/Libraries/DolphinLocalApi.php`: login-with-token otomatis saat 401, lalu start/stop.
- Settings: kolom **Local API URL** (default `http://localhost:3001`) dan tombol **Check Local API**.
- URL dibatasi ke loopback (`localhost`, `127.0.0.1`, `[::1]`); skema/userinfo/path/query aneh ditolak.
- Pesan error yang jelas saat aplikasi Dolphin tidak berjalan / tidak terotorisasi / profil tidak ditemukan.
- Tombol **Check Connection** sebelumnya palsu (selalu "berhasil" jika token terisi tanpa menghubungi server);
  kini benar-benar memanggil API dan mengenali token ditolak.

## Dokumentasi
- 24 file di `docs/` + `QUICKSTART.md` + `DEBUG_PROFILE_PAGE.md` diganti dengan `README.md` dan `docs/API.md` (dibuat dari `Routes.php`).

## Penamaan file (lebih umum)
| Lama | Baru |
|---|---|
| `backend_gui.py` | `backend.py` |
| `run_backend_gui.bat` | `run_gui.bat` |
| `run_backend_headless.bat` | `run_headless.bat` |
| `stop_all_bayzbot.bat` | `stop_all.bat` |

- Launcher kini menjalankan Python dengan path lengkap (`python -u "%~dp0backend.py"`), dan skrip
  stop (`stop_all.bat` serta tombol Stop di Settings) hanya mencocokkan skrip di folder proyek ini,
  sehingga `backend.py` milik proyek lain tidak ikut dihentikan. Nama lama masih dikenali.
- `gui_config.json` tidak diubah.

## Perbaikan: Telegram, threshold RAM, iklan, Watchdog
- **Telegram**: `/stopstream`, `/pause`, `/play` (`/resume`), `/next`, `/prev`, `/restart` (opsional `id_profil`) kini
  dikirim lewat jalur yang sama dengan tombol di web (perintah ke ekstensi + status sesi), jadi halaman web ikut berubah.
  Sebelumnya `/stopstream` hanya mengubah flag di Python sehingga musik tetap berjalan. `/status` menampilkan status per sesi.
- **Threshold RAM**: halaman Stream tidak menerima nilai dari Settings (selalu 85) dan backend memakai konstanta 85.
  Kini nilai Settings (1-100, divalidasi) dipakai halaman, dikirim ke backend (`POST /api/settings`, juga ikut `/api/play`)
  dan disimpan di `gui_config.json` (`ram_threshold_percent`).
- **Iklan**: iklan kedua dalam satu rangkaian terdeteksi sebagai iklan baru. Aturan skip memakai "Skip Iklan Delay (detik)"
  di Settings sebagai batas: iklan yang durasinya sampai batas itu diputar sampai selesai, iklan yang lebih lama di-skip
  (tombol Skip diklik setelah batas terlewati). Iklan tidak pernah dimute atau dipercepat; iklan tanpa tombol Skip dibiarkan
  sampai selesai dan hanya dilaporkan di LOG. Event `ended` milik iklan tidak lagi memindah lagu. Polling iklan tiap 0,5 detik.
- **LOG/Watchdog**: Watchdog melapor tiap interval (juga saat dijeda dan untuk playlist GUI) lengkap dengan status iklan
  (iklan terdeteksi, keputusan putar penuh/skip, menunggu Skip, Skip diklik, total di-skip/diputar penuh). Iklan > 90 detik ditandai WARNING.

## Belum ditangani
- Token Dolphin/Telegram masih plaintext di tabel `settings`.
- Belum ada kepemilikan data per user; `/users` dan `/log` masih stub.
- `Stream::start` mereset `total_plays` ke 0 setiap start.
- `/logout` masih lewat GET.
- Start/stop hanya bekerja jika Dolphin{anty} berjalan di mesin yang sama dengan server PHP.

## Pengujian
Dijalankan dengan CodeIgniter 4.7.0 + PHP 8.3 pada DB kosong: 11 migrasi sukses, seeder
(termasuk idempotensi), dan uji HTTP untuk setup, CSRF, login, AJAX, serta alur instalasi baru.
