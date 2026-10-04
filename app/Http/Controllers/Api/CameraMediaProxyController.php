<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CameraSetting;
use App\Services\FrigateSessionClient;
use App\Support\CameraMediaToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

/**
 * GET /api/camera-media/{token}/{filename} — route PUBLIC (không qua auth:sanctum vì player HLS
 * không tự gắn Bearer token được) — bảo mật bằng CameraMediaToken (HMAC, ngắn hạn) thay vào đó.
 * Forward VOD/HLS từ Frigate mà KHÔNG BAO GIỜ lộ URL/cookie Frigate thật ra ngoài trình duyệt.
 */
class CameraMediaProxyController extends Controller
{
    public function __invoke(Request $request, string $token, string $filename)
    {
        $data = CameraMediaToken::verify($token);
        if (! $data) {
            abort(403, 'Invalid or expired media token.');
        }

        $setting = CameraSetting::forPartner($data['partner_id'] ?? null);
        if (! $setting->isConfigured()) {
            abort(404, 'Camera server is not configured.');
        }

        $cookie = app(FrigateSessionClient::class)->cookieFor($setting);
        $url = rtrim($setting->base_url, '/') . $data['path'];

        try {
            if ($data['transcode'] ?? false) {
                return $this->streamTranscoded($url, $cookie);
            }

            return $this->streamPassthrough($request, $url, $cookie);
        } catch (\Throwable) {
            abort(502, 'Failed to reach camera server.');
        }
    }

    // H.264 — forward nguyên văn, hỗ trợ Range để trình duyệt tua video được.
    private function streamPassthrough(Request $request, string $url, ?string $cookie): Response
    {
        $headers = $cookie ? ['Cookie' => $cookie] : [];
        if ($range = $request->header('Range')) {
            $headers['Range'] = $range;
        }

        $response = Http::timeout(15)->withHeaders($headers)->get($url);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?: 'application/octet-stream')
            ->header('Accept-Ranges', 'bytes');
    }

    // H.265 (HEVC) — trình duyệt không phát trực tiếp được, cần ffmpeg pipe sang MP4 phân mảnh.
    private function streamTranscoded(string $url, ?string $cookie): Response
    {
        $ffmpegBinary = config('services.ffmpeg.binary', 'ffmpeg');
        $cookieHeader = $cookie ? "Cookie: {$cookie}" : '';

        $process = proc_open([
            $ffmpegBinary,
            '-headers', $cookieHeader,
            '-i', $url,
            '-c:v', 'libx264',
            '-c:a', 'aac',
            '-movflags', 'frag_keyframe+empty_moov',
            '-f', 'mp4',
            'pipe:1',
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            abort(502, 'Failed to start transcoding.');
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return response($output, 200)->header('Content-Type', 'video/mp4');
    }
}
