# Daftar Endpoint

> Dibuat otomatis dari `app/Config/Routes.php`. Jika rute berubah, buat ulang daftar ini.

**Akses:** `login` = wajib login; `setup*` = hanya saat instalasi baru (belum ada user) atau setelah login; `publik` = tanpa login.

**CSRF:** semua request `POST`/`DELETE` wajib menyertakan token. Form HTML memakai `csrf_field()`; `fetch()` otomatis ditambah header `X-CSRF-TOKEN` oleh `public/assets/csrf.js` (token dibaca dari `<meta name="csrf-token">`). Klien non-browser harus mengirim header itu sendiri.

## Halaman & auth

| Method | Path | Handler | Akses |
|---|---|---|---|
| GET | `/` | `Home::index` | publik |
| GET | `/setup` | `Setup::check` | setup* |
| GET | `/setup/check` | `Setup::check` | setup* |
| GET | `/setup/runChecks` | `Setup::runChecks` | setup* |
| POST | `/setup/runMigrations` | `Setup::runMigrations` | setup* |
| GET | `/login/checkSetup` | `Auth::checkSetup` | setup* |
| GET | `/login` | `Auth::login` | publik |
| POST | `/login` | `Auth::attempt` | publik |
| POST | `/login/attempt` | `Auth::attempt` | publik |
| GET | `/logout` | `Auth::logout` | publik |
| GET | `/browser` | `Monitoring::index` | login |
| GET | `/proxies` | `Proxies::index` | login |
| GET | `/playlist` | `Playlist::index` | login |
| GET | `/stream` | `Stream::index` | login |
| GET | `/users` | `Users::index` | login |
| GET | `/log` | `Log::index` | login |
| GET | `/settings` | `Settings::index` | login |

## Settings

| Method | Path | Handler | Akses |
|---|---|---|---|
| POST | `/settings/save` | `Settings::save` | login |
| POST | `/settings/check-connection` | `Settings::checkConnection` | login |
| POST | `/settings/check-local` | `Settings::checkLocal` | login |
| POST | `/settings/check-bot-bridge` | `Settings::checkBotBridge` | login |
| POST | `/settings/test-telegram` | `Settings::testTelegram` | login |

## Profil Dolphin{anty}

| Method | Path | Handler | Akses |
|---|---|---|---|
| GET | `/api/profiles` | `Monitoring::getProfiles` | login |
| POST | `/api/start/(:num)` | `Monitoring::startProfile/$1` | login |
| POST | `/api/stop/(:num)` | `Monitoring::stopProfile/$1` | login |

## Proxy

| Method | Path | Handler | Akses |
|---|---|---|---|
| GET | `/api/proxy/all` | `Proxies::getAll` | login |
| GET | `/api/proxy/export-dolphin` | `Proxies::exportForDolphin` | login |
| GET | `/api/proxy/(:num)` | `Proxies::getById/$1` | login |
| POST | `/api/proxy/create` | `Proxies::create` | login |
| POST | `/api/proxy/update/(:num)` | `Proxies::update/$1` | login |
| DELETE/POST | `/api/proxy/delete/(:num)` | `Proxies::delete/$1` | login |
| POST | `/api/proxy/test/(:num)` | `Proxies::test/$1` | login |
| POST | `/api/proxy/test-connection` | `Proxies::testConnection` | login |
| POST | `/api/proxy/toggle/(:num)` | `Proxies::toggle/$1` | login |
| POST | `/api/proxy/fetch-dolphin` | `Proxies::fetchFromDolphinAPI` | login |
| POST | `/api/proxy/sync-extension` | `Proxies::syncFromExtension` | login |

## Playlist

| Method | Path | Handler | Akses |
|---|---|---|---|
| GET | `/api/playlist/all` | `Playlist::getAll` | login |
| GET | `/api/playlist/(:num)` | `Playlist::getById/$1` | login |
| GET | `/api/playlist/(:num)/songs` | `Playlist::getSongs/$1` | login |
| POST | `/api/playlist/create` | `Playlist::create` | login |
| POST | `/api/playlist/update/(:num)` | `Playlist::update/$1` | login |
| DELETE | `/api/playlist/delete/(:num)` | `Playlist::delete/$1` | login |
| POST | `/api/playlist/(:num)/song/add` | `Playlist::addSong/$1` | login |
| DELETE | `/api/playlist/song/remove/(:num)` | `Playlist::removeSong/$1` | login |
| POST | `/api/playlist/song/refresh/(:num)` | `Playlist::refreshSong/$1` | login |
| POST | `/api/playlist/song/status/(:num)` | `Playlist::updateSongStatus/$1` | login |
| POST | `/api/playlist/upload-file` | `Playlist::uploadPlaylistFile` | login |
| POST | `/api/playlist/check-urls` | `Playlist::checkAndFetchYoutubeData` | login |
| POST | `/api/playlist/save-bulk` | `Playlist::saveBulkSongs` | login |

## Stream

| Method | Path | Handler | Akses |
|---|---|---|---|
| GET | `/api/stream/all` | `Stream::getAll` | login |
| GET | `/api/stream/active` | `Stream::getActive` | login |
| GET | `/api/stream/profile/(:num)` | `Stream::getByProfile/$1` | login |
| GET | `/api/stream/stats/(:num)` | `Stream::getStats/$1` | login |
| GET | `/api/stream/(:num)` | `Stream::getById/$1` | login |
| POST | `/api/stream/create` | `Stream::create` | login |
| POST | `/api/stream/update/(:num)` | `Stream::update/$1` | login |
| DELETE | `/api/stream/delete/(:num)` | `Stream::delete/$1` | login |
| POST | `/api/stream/start/(:num)` | `Stream::start/$1` | login |
| POST | `/api/stream/stop/(:num)` | `Stream::stop/$1` | login |
