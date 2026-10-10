<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (! session()->get('logged_in')) {
            return redirect()->to(site_url('login'));
        }

        // Halaman pertama setelah login = menu teratas (Stream)
        return redirect()->to(site_url('stream'));
    }
}
