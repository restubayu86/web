<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Daftar menu sidebar. Urutan array = urutan tampil dari atas.
 *
 * Kolom:
 *  - key     : id unik
 *  - label   : teks menu
 *  - path    : path relatif terhadap baseURL ('' = halaman utama)
 *  - icon    : kelas Bootstrap Icons
 *  - enabled : false untuk menyembunyikan menu tanpa menghapusnya
 *  - bottom  : true agar item didorong ke bawah sidebar (gaya Tailwind)
 */
class Menu extends BaseConfig
{
    /**
     * @var list<array{key:string,label:string,path:string,icon:string,enabled?:bool,bottom?:bool}>
     */
    public array $items = [
        ['key' => 'stream',   'label' => 'Stream',   'path' => 'stream',   'icon' => 'bi-broadcast'],
        ['key' => 'browser',  'label' => 'Browser',  'path' => 'browser',  'icon' => 'bi-browser-chrome'],
        ['key' => 'proxies',  'label' => 'Proxies',  'path' => 'proxies',  'icon' => 'bi-globe'],
        ['key' => 'playlist', 'label' => 'Playlist', 'path' => 'playlist', 'icon' => 'bi-collection-play'],
        ['key' => 'log',      'label' => 'Log',      'path' => 'log',      'icon' => 'bi-journal-text'],
        ['key' => 'settings', 'label' => 'Settings', 'path' => 'settings', 'icon' => 'bi-gear', 'bottom' => true],
    ];
}
