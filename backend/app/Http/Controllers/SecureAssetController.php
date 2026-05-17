<?php

namespace App\Http\Controllers;

use App\Support\SecureAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class SecureAssetController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        try {
            $path = SecureAsset::normalizePath((string) $data['path']);
            $absolutePath = SecureAsset::absolutePath($path);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_unless(File::exists($absolutePath), 404);
        abort_unless(str_starts_with((string) (File::mimeType($absolutePath) ?: ''), 'image/'), 404);

        return response()->file($absolutePath, [
            'Cache-Control' => 'private, max-age=300, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.basename($absolutePath).'"',
        ]);
    }
}
