<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Halaman utama
$routes->get('/', 'Home::index');

// Setup awal (hanya terbuka saat instalasi baru atau setelah login, lihat SetupFilter)
$routes->group('', ['filter' => 'setup'], static function (RouteCollection $routes) {
    $routes->get('setup', 'Setup::check');
    $routes->get('setup/check', 'Setup::check');
    $routes->get('setup/runChecks', 'Setup::runChecks');
    $routes->post('setup/runMigrations', 'Setup::runMigrations');
    $routes->get('login/checkSetup', 'Auth::checkSetup');
});

// Autentikasi
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->post('login/attempt', 'Auth::attempt'); // alias lama, dipertahankan agar form lama tetap jalan
$routes->get('logout', 'Auth::logout');
$routes->post('auth/verify-password', 'Auth::verifyPassword');
$routes->post('auth/change-password', 'Auth::changePassword');

// Semua rute di bawah ini wajib login
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {

    // Halaman
    $routes->get('browser', 'Monitoring::index');
    $routes->get('chromium', 'Chromium::index');
    $routes->get('proxies', 'Proxies::index');
    $routes->get('playlist', 'Playlist::index');
    $routes->get('stream', 'Stream::index');
    $routes->get('users', 'Users::index');
    $routes->get('log', 'Log::index');
    $routes->get('settings', 'Settings::index');
    $routes->get('settings/tutorial', 'Settings::tutorial');

    // Settings
    $routes->post('settings/save', 'Settings::save');
    $routes->get('api/settings/revenue', 'Settings::getRevenueRates');
    $routes->post('settings/check-connection', 'Settings::checkConnection');
    $routes->post('settings/check-local', 'Settings::checkLocal');
    $routes->post('settings/check-bot-bridge', 'Settings::checkBotBridge');
    $routes->post('settings/test-telegram', 'Settings::testTelegram');
    $routes->post('settings/check-python-health', 'Settings::checkPythonHealth');
    $routes->post('settings/stop-python', 'Settings::stopPython');
    $routes->get('settings/get-python-logs', 'Settings::getPythonLogs');
    $routes->get('settings/get-ram-usage', 'Settings::getRamUsage');
    $routes->post('settings/send-ram-notification', 'Settings::sendRamNotification');
    $routes->post('settings/reset-database', 'Settings::resetDatabase');

    // API: profil Dolphin{anty}
    $routes->get('api/profiles', 'Monitoring::getProfiles');
    $routes->post('api/start/(:num)', 'Monitoring::startProfile/$1');
    $routes->post('api/stop/(:num)', 'Monitoring::stopProfile/$1');

    // API: profil Chromium lokal
    $routes->get('api/chromium/profiles', 'Chromium::getProfiles');
    $routes->post('api/chromium/profiles', 'Chromium::createProfile');
    $routes->post('api/chromium/profiles/(:num)/update', 'Chromium::updateProfile/$1');
    $routes->post('api/chromium/profiles/(:num)/delete', 'Chromium::deleteProfile/$1');
    $routes->post('api/chromium/profiles/(:num)/start', 'Chromium::startProfile/$1');
    $routes->post('api/chromium/profiles/(:num)/stop', 'Chromium::stopProfile/$1');
    $routes->post('api/chromium/executable', 'Chromium::saveExecutable');
    $routes->get('api/chromium/proxies', 'Chromium::getProxies');

    // API: proxy
    $routes->get('api/proxy/all', 'Proxies::getAll');
    $routes->get('api/proxy/dolphin-options', 'Proxies::getDolphinOptions');
    $routes->post('api/proxy/dolphin-test/(:num)', 'Proxies::testDolphinProxy/$1');
    $routes->get('api/proxy/export-dolphin', 'Proxies::exportForDolphin');
    $routes->get('api/proxy/(:num)', 'Proxies::getById/$1');
    $routes->post('api/proxy/create', 'Proxies::create');
    $routes->post('api/proxy/update/(:num)', 'Proxies::update/$1');
    $routes->match(['delete', 'post'], 'api/proxy/delete/(:num)', 'Proxies::delete/$1');
    $routes->post('api/proxy/test/(:num)', 'Proxies::test/$1');
    $routes->post('api/proxy/test-connection', 'Proxies::testConnection');
    $routes->post('api/proxy/toggle/(:num)', 'Proxies::toggle/$1');
    $routes->post('api/profiles/(:num)/proxy', 'Proxies::assignToProfile/$1');
    $routes->post('api/proxy/fetch-dolphin', 'Proxies::fetchFromDolphinAPI');
    $routes->post('api/proxy/sync-extension', 'Proxies::syncFromExtension');

    // API: playlist
    $routes->get('api/playlist/all', 'Playlist::getAll');
    $routes->post('api/profiles/(:num)/playlist', 'Playlist::assignToProfile/$1');
    $routes->post('api/playlist/song/duration/(:num)', 'Playlist::refreshSongDuration/$1');
    $routes->get('api/playlist/(:num)', 'Playlist::getById/$1');
    $routes->get('api/playlist/(:num)/songs', 'Playlist::getSongs/$1');
    $routes->post('api/playlist/create', 'Playlist::create');
    $routes->post('api/playlist/update/(:num)', 'Playlist::update/$1');
    $routes->delete('api/playlist/delete/(:num)', 'Playlist::delete/$1');
    $routes->post('api/playlist/(:num)/song/add', 'Playlist::addSong/$1');
    $routes->delete('api/playlist/song/remove/(:num)', 'Playlist::removeSong/$1');
    $routes->post('api/playlist/song/refresh/(:num)', 'Playlist::refreshSong/$1');
    $routes->post('api/playlist/song/status/(:num)', 'Playlist::updateSongStatus/$1');
    $routes->post('api/playlist/upload-file', 'Playlist::uploadPlaylistFile');
    $routes->post('api/playlist/check-urls', 'Playlist::checkAndFetchYoutubeData');
    $routes->post('api/playlist/save-bulk', 'Playlist::saveBulkSongs');

    // API: stream
    $routes->get('api/stream/all', 'Stream::getAll');
    $routes->get('api/stream/active', 'Stream::getActive');
    $routes->get('api/stream/profile/(:num)', 'Stream::getByProfile/$1');
    $routes->get('api/stream/stats/(:num)', 'Stream::getStats/$1');
    $routes->get('api/stream/chart/(:num)', 'Stream::getChart/$1');
    $routes->get('api/stream/(:num)', 'Stream::getById/$1');
    $routes->post('api/stream/create', 'Stream::create');
    $routes->post('api/stream/update/(:num)', 'Stream::update/$1');
    $routes->delete('api/stream/delete/(:num)', 'Stream::delete/$1');
    $routes->post('api/stream/start/(:num)', 'Stream::start/$1');
    $routes->post('api/stream/stop/(:num)', 'Stream::stop/$1');
    $routes->post('api/stream/control/(:num)/(:segment)', 'Stream::control/$1/$2');

    // API: Python monitoring
    $routes->get('api/check-python-health', 'Settings::checkPythonHealth');
    $routes->post('api/stop-python', 'Settings::stopPython');
    $routes->get('api/get-python-logs', 'Settings::getPythonLogs');

    // API: logs
    $routes->get('api/logs', 'Log::getAll');
    $routes->delete('api/logs/clear', 'Log::clear');
});
