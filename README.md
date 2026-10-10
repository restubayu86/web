# Dolphin{anty} Monitoring

Dashboard web (CodeIgniter 4.7, PHP 8.2+, SQLite) untuk mengelola:

- **Profil** browser Dolphin{anty} (daftar lewat API remote; start/stop lewat Local API)
- **Proxy** (CRUD, tes koneksi, sinkron/ekspor ke Dolphin)
- **Playlist** video YouTube dan daftar lagunya (impor URL/file, ambil metadata judul/durasi)
- **Stream**: pasangan profil + playlist beserta status dan statistiknya
- **Settings**: token Dolphin, BayzBot Python bridge, shuffle, loop, watchdog, dan jeda skip iklan

`Stream::start/stop` tetap mengelola data stream di database. Tombol Start/Stop profil
menjalankan Dolphin melalui Local API. Start juga menyalakan backend BayzBot bila perlu,
menunggu ekstensi pada profil tersebut, lalu memutar playlist yang ditetapkan.

## Instalasi & Cara Menjalankan

### 1. Persiapan Awal
Pastikan PHP 8.2+ dan Composer terinstal.
```bash
composer install
cp .env.example .env        # sesuaikan isinya (app.baseURL, database, dll)
php spark migrate
php spark db:seed AdminSeeder
```

### 2. Menjalankan Web Server (PHP)
Gunakan perintah berikut untuk menjalankan server lokal dengan dukungan routing:
```powershell
php -S 127.0.0.1:8080 -t public public/router.php
```
Akses dashboard di: `http://127.0.0.1:8080`

### 3. Menjalankan Python Backend (BayzBot)
Aplikasi ini memiliki fitur **Autostart** sehingga Anda tidak perlu menjalankan Python secara manual:
1.  Buka halaman **Settings**.
2.  Klik tombol **Check Health** pada panel *Python Backend Status*.
3.  Jika Python belum aktif, sistem akan menyalakannya di background. Status akan berubah menjadi **⏳ Python Booting**.
4.  Tunggu ~5 detik, lalu klik **Check Health** lagi hingga status menjadi **✓ Python Running** (Hijau).
5.  Klik **Test Python Bridge** untuk memastikan koneksi Web ke Python berhasil.

### 4. Monitoring RAM
Aplikasi ini dilengkapi dengan fitur monitoring RAM:
1.  Buka halaman **Settings**.
2.  Konfigurasikan **Threshold RAM (%)** (default 85%).
3.  Aktifkan **Notifikasi RAM Tinggi** jika ingin menerima peringatan via Telegram.
4.  Aktifkan **Auto-stop streams** jika ingin sistem otomatis menghentikan semua stream saat RAM melebihi threshold.
5.  Pantau penggunaan RAM secara real-time di halaman **Stream Monitoring** pada panel **RAM Monitor**.

---

## Konfigurasi (`.env`)

| Kunci | Keterangan |
|---|---|
| `CI_ENVIRONMENT` | `development` atau `production`. |
| `app.baseURL` | Gunakan `http://127.0.0.1:8080/` untuk menghindari masalah CORS/CSRF. |
| `database.default.DBDriver` | `SQLite3` (file di `writable/database.sqlite`) |
| `admin.username`, `admin.password` | Hanya dipakai `AdminSeeder` |

Token Dolphin, URL Local API, dan Telegram diatur melalui halaman **Settings**.

## Start/Stop profil (Local API Dolphin{anty})

Daftar profil diambil dari API remote (`anty-api.com`), tetapi **start/stop memakai Local API**
sesuai dokumentasi resmi Dolphin:

| Langkah | Request |
|---|---|
| Login (otomatis jika 401) | `POST {local}/v1.0/auth/login-with-token` `{"token": "..."}` |
| Start | `GET {local}/v1.0/browser_profiles/{id}/start` |
| Stop | `GET {local}/v1.0/browser_profiles/{id}/stop` |

Syarat: aplikasi Dolphin{anty} berjalan **di mesin yang sama dengan server ini**. URL Local API
(default `http://localhost:3001`) diatur di Settings; jika port 3001 terpakai, lihat port di jendela *Health*
pada aplikasi Dolphin. Hanya `localhost` / `127.0.0.1` yang diterima. Tombol **Check Local API**
di Settings memverifikasi koneksi dan token.

## Integrasi playback BayzBot

- **Backend Headless**: Otomatis dikelola oleh web. HTTP API mendengarkan `127.0.0.1:8766`.
- **Ekstensi**: Hubungkan ekstensi Chrome ke `ws://localhost:8765` dan isi **Dolphin Browser Profile ID** pada Options ekstensi.
- **Log**: Log Python (stdout/stderr) disimpan di `writable/logs/bayzbot-*.log` dan dapat dilihat langsung di halaman Settings.
- **Troubleshooting**:
    - **Stuck Loading**: Jika status Python tidak berubah, pastikan tidak ada proses `python.exe` yang menggantung di Task Manager, lalu klik **Stop Python** di Settings.
    - **Failed to Fetch**: Pastikan Anda mengakses web melalui IP `127.0.0.1:8080`, bukan `localhost:8080` jika terjadi masalah origin.
    - **HTTP 500 pada Log**: Pastikan folder `writable/logs` ada dan dapat ditulis oleh PHP.

## Struktur

```
app/Controllers   Auth, Setup, Monitoring (Dolphin), Proxies, Playlist, Stream, Settings
app/Libraries     DolphinLocalApi (klien Local API: login, start, stop)
app/Filters       AuthFilter (wajib login), SetupFilter (setup hanya saat instalasi baru)
app/Helpers       error_helper (client_error), menu_helper (menu_items: menu + status aktif)
app/Config/Menu.php  Daftar menu sidebar (urutan, label, path, ikon, enabled)
app/Views/partials   sidebar_tailwind & sidebar_classic (dirender dari Config\Menu)
app/Models        Playlist, PlaylistSong, Profile, Proxy, Settings, Stream, User
app/Views         Satu file per halaman
public/assets     csrf.js (menambah header CSRF ke fetch)
docs/API.md       Daftar endpoint (dibuat dari Routes.php)
```

## Keamanan

- Semua halaman dan API di balik login, kecuali `/login`, `/logout`, dan `/` (hanya mengalihkan: ke `/stream` jika sudah login, ke `/login` jika belum).
- CSRF aktif global. Token dibagikan lewat `<meta name="csrf-token">`; `fetch()` non-GET
  otomatis menyertakan header `X-CSRF-TOKEN`.
- Endpoint setup/migrasi hanya terbuka saat belum ada user, atau setelah login.
- Jangan commit `.env` maupun `writable/database.sqlite`.
- Di production: set `CI_ENVIRONMENT = production` dan jalankan lewat HTTPS.

## Batasan yang diketahui

- Token Dolphin/Telegram tersimpan plaintext di database.
- Belum ada pemisahan data per user (semua user yang login melihat semuanya).
- Halaman `/users` dan `/log` masih stub.

## Mengubah menu sidebar

Semua menu ada di `app/Config/Menu.php`. Urutan array = urutan dari atas:
Stream, Browser (`/browser`), Proxies, Playlist, Users, Log, Settings.

- Ubah urutan: pindahkan barisnya.
- Ubah teks atau ikon: edit `label` / `icon` (Bootstrap Icons).
- Sembunyikan menu: tambahkan `'enabled' => false`.
- Menu baru: tambahkan satu baris (`key`, `label`, `path`, `icon`), tidak perlu mengedit view.

Menu yang aktif ditentukan otomatis dari URL, termasuk sub-path (mis. `/playlist/...`).
