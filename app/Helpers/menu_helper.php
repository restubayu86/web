<?php

if (! function_exists('menu_url')) {
    /**
     * URL menu berbasis root ("/proxies"), tetap benar jika aplikasi di subfolder.
     */
    function menu_url(string $path): string
    {
        $basePath = rtrim((string) parse_url(base_url(), PHP_URL_PATH), '/');

        return $basePath . '/' . ltrim($path, '/');
    }
}

if (! function_exists('menu_items')) {
    /**
     * Daftar menu yang aktif, lengkap dengan url dan status "active".
     *
     * @param string|null $currentPath Path saat ini (default: URL request).
     *
     * @return list<array{key:string,label:string,path:string,icon:string,url:string,active:bool,bottom:bool}>
     */
    function menu_items(?string $currentPath = null): array
    {
        $current = trim($currentPath ?? uri_string(), '/');
        $items   = [];

        foreach (config('Menu')->items as $item) {
            if (($item['enabled'] ?? true) === false) {
                continue;
            }

            $path = trim($item['path'], '/');

            $item['active'] = $path === ''
                ? $current === ''
                : ($current === $path || str_starts_with($current, $path . '/'));
            $item['url']    = menu_url($path);
            $item['bottom'] = (bool) ($item['bottom'] ?? false);
            $items[]        = $item;
        }

        return $items;
    }
}
