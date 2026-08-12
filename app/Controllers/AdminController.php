<?php

require_once __DIR__ . '/../Database/Initialize.php';

class AdminController
{
    public function __construct()
    {
        Initialize::run();
    }

    public function dashboard(): void
    {
        $this->view('dashboard');
    }

    public function pages(): void
    {
        $this->view('pages');
    }

    public function verein(): void
    {
        $this->view('verein');
    }

    public function strecken(): void
    {
        $this->view('strecken');
    }

    public function onroad(): void
    {
        $this->view('onroad');
    }

    public function offroad(): void
    {
        $this->view('offroad');
    }

    public function reglement(): void
    {
        $this->view('reglement');
    }

    public function mitgliedschaft(): void
    {
        $this->view('mitgliedschaft');
    }

    public function sponsoren(): void
    {
        $this->view('sponsoren');
    }

    public function social(): void
    {
        $this->view('social');
    }

    public function wartung(): void
    {
        $this->view('wartung');
    }

    public function media(): void
    {
        $this->view('media');
    }

    public function images(): void
    {
        $this->view('images');
    }

    public function documents(): void
    {
        $this->view('documents');
    }

    public function downloads(): void
    {
        $this->view('downloads');
    }

    public function settings(): void
    {
        $this->view('settings');
    }

    public function users(): void
    {
        $this->view('users');
    }

    public function backup(): void
    {
        $this->view('backup');
    }

    public function logs(): void
    {
        $this->view('logs');
    }

    private function view(string $name): void
    {
        $file = __DIR__ .
            '/../Views/admin/' .
            $name .
            '.php';

        if (!file_exists($file)) {

            http_response_code(404);

            echo 'Admin-Seite nicht gefunden.';

            return;
        }

        require $file;
    }
}
