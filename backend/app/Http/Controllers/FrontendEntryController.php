<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FrontendEntryController extends Controller
{
    public function site(?string $path = null): BinaryFileResponse
    {
        return $this->serve('site');
    }

    public function client(?string $path = null): BinaryFileResponse
    {
        return $this->serve('client');
    }

    public function admin(?string $path = null): BinaryFileResponse
    {
        return $this->serve('admin');
    }

    private function serve(string $application): BinaryFileResponse
    {
        $entryPath = (string) config("frontend.entries.{$application}");

        abort_unless(File::isFile($entryPath), 404);

        return response()->file($entryPath, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
