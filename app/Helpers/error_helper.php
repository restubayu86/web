<?php

if (! function_exists('client_error')) {
    /**
     * Pesan error yang aman dikirim ke browser.
     *
     * Detail lengkap selalu ditulis ke log. Di production klien hanya menerima
     * pesan umum; di development detail ikut ditampilkan agar mudah debug.
     */
    function client_error(\Throwable $e, string $summary = 'Terjadi kesalahan'): string
    {
        log_message('error', '{summary}: {message} ({file}:{line})', [
            'summary' => $summary,
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);

        if (ENVIRONMENT === 'production') {
            return $summary . '. Lihat log server untuk detailnya.';
        }

        return $summary . ': ' . $e->getMessage();
    }
}
