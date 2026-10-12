<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ScreenController extends Controller
{
    /**
     * Serve the latest desktop screenshot written by the capture service.
     */
    public function shot(): BinaryFileResponse|Response
    {
        $path = config('systeminfo.screen_path');

        if (! is_string($path) || ! is_file($path)) {
            return response()->json([
                'error' => 'screenshot_not_available',
                'message' => 'Capture service has not produced a frame yet.',
            ], 503);
        }

        $modifiedAt = filemtime($path) ?: time();

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Screenshot-Time' => (string) $modifiedAt,
        ]);
    }
}
