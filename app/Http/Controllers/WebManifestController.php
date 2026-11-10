<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebManifestController extends Controller
{
    /**
     * The web app manifest that lets the app be installed on an iPad home screen (or any
     * device) and open full-screen. The name follows APP_NAME so a rename carries over.
     */
    public function __invoke(): JsonResponse
    {
        $name = (string) config('app.name');

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => $name,
            'description' => 'Catalog, search and sell your sports card collection.',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#edf0f3',
            'theme_color' => '#15213a',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/apple-touch-icon.png', 'sizes' => '180x180', 'type' => 'image/png'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json'], options: JSON_UNESCAPED_SLASHES);
    }
}
