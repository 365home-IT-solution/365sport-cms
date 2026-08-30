<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectionsConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Proxy dẫn đường cho bản đồ /ban-do (map-page.blade.php) — API key OpenRouteService giữ RIÊNG ở
// backend, KHÔNG BAO GIỜ lộ ra JS phía trình duyệt (khác với gọi thẳng OSRM công cộng trước đây,
// vốn không cần key nên gọi trực tiếp từ JS được). avoid_countries chặn ORS định tuyến qua Lào/
// Campuchia ngay tại nguồn — chính xác hơn nhiều so với cách tự lọc điểm ngoài lãnh thổ VN
// (routeStaysInVietnam() ở map-page.blade.php, vẫn giữ lại làm phương án khi tắt tính năng này).
class DirectionsController extends Controller
{
    public function route(Request $request): JsonResponse
    {
        $request->validate([
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat'   => ['required', 'numeric', 'between:-90,90'],
            'to_lng'   => ['required', 'numeric', 'between:-180,180'],
        ]);

        $config = DirectionsConfiguration::first();

        if (! $config || ! $config->enabled || ! $config->ors_api_key) {
            return response()->json(['message' => 'Dẫn đường qua OpenRouteService hiện đang tắt.', 'fallback' => true], 422);
        }

        // Hết quota hôm nay (biết từ lần gọi gần nhất, xem saveQuotaFromResponse()) mà chưa tới giờ
        // reset — TỰ ĐỘNG báo 'fallback' để frontend chuyển sang OSRM ngay, không tốn thêm 1 lượt
        // gọi ORS chắc chắn sẽ bị từ chối (429). Qua giờ reset thì bỏ qua kiểm tra này, để lần gọi
        // thật tiếp theo tự xác nhận + cập nhật lại quota mới (không tự đoán quota đã đầy lại).
        if (
            ! is_null($config->quota_remaining)
            && $config->quota_remaining <= 1
            && $config->quota_reset_at
            && $config->quota_reset_at->isFuture()
        ) {
            return response()->json(['message' => 'Đã hết quota OpenRouteService hôm nay, tạm dùng OSRM.', 'fallback' => true], 422);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $config->ors_api_key,
                'Content-Type'  => 'application/json',
            ])
                ->timeout(10)
                ->post('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                    'coordinates' => [
                        [(float) $request->from_lng, (float) $request->from_lat],
                        [(float) $request->to_lng, (float) $request->to_lat],
                    ],
                    // Chặn định tuyến qua Lào (LA)/Campuchia (KH) — đúng vấn đề gốc cần giải quyết.
                    'options' => ['avoid_countries' => ['LA', 'KH']],
                ]);

            $this->saveQuotaFromResponse($config, $response);

            if ($response->status() === 429) {
                Log::info('DirectionsController: ORS quota exceeded, falling back to OSRM', ['config_id' => $config->id]);

                return response()->json(['message' => 'Đã hết quota OpenRouteService hôm nay, tạm dùng OSRM.', 'fallback' => true], 422);
            }

            if (! $response->successful()) {
                Log::warning('DirectionsController: ORS request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return response()->json(['message' => 'Không tìm được tuyến đường phù hợp.'], 422);
            }

            $data    = $response->json();
            $feature = $data['features'][0] ?? null;

            if (! $feature) {
                return response()->json(['message' => 'Không tìm được tuyến đường phù hợp.'], 422);
            }

            $segment = $feature['properties']['segments'][0] ?? null;

            return response()->json([
                'distance' => $segment['distance'] ?? null,
                'duration' => $segment['duration'] ?? null,
                // Cùng thứ tự [lng, lat] như OSRM geometry.coordinates — map-page.blade.php dùng
                // chung 1 chỗ chuyển đổi sang [lat, lng] cho Leaflet cho cả 2 luồng ORS/OSRM.
                'coordinates' => $feature['geometry']['coordinates'] ?? [],
                'steps' => collect($segment['steps'] ?? [])->map(fn ($s) => [
                    'type'        => $s['type'] ?? null,
                    'instruction' => $s['instruction'] ?? '',
                    'name'        => $s['name'] ?? '',
                    'distance'    => $s['distance'] ?? 0,
                ])->values(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('DirectionsController: ORS request exception', ['error' => $e->getMessage()]);

            // Lỗi kỹ thuật (mạng/timeout/ORS sập...) — không phải "không tìm được đường", tạm dùng
            // OSRM thay vì chặn hẳn tính năng chỉ đường của khách.
            return response()->json(['message' => 'Không kết nối được OpenRouteService, tạm dùng OSRM.', 'fallback' => true], 422);
        }
    }

    // Lưu lại quota mới nhất đọc từ header X-Ratelimit-* của MỖI response thật (kể cả khi request
    // lỗi, ORS vẫn trả kèm header này) — chính xác theo thời gian thực, không phụ thuộc dashboard
    // web của ORS (từng thấy hiển thị trễ, không giảm dù request đã tính vào quota thật). Hiển thị
    // lại ở admin (App\Filament\Pages\DirectionsSettings).
    private function saveQuotaFromResponse(DirectionsConfiguration $config, \Illuminate\Http\Client\Response $response): void
    {
        $limit     = $response->header('X-Ratelimit-Limit');
        $remaining = $response->header('X-Ratelimit-Remaining');
        $reset     = $response->header('X-Ratelimit-Reset');

        if ($limit === '' || $remaining === '') {
            return;
        }

        $config->update([
            'quota_limit'      => (int) $limit,
            'quota_remaining'  => (int) $remaining,
            'quota_reset_at'   => $reset !== '' ? \Carbon\Carbon::createFromTimestamp((int) $reset) : null,
            'quota_checked_at' => now(),
        ]);
    }
}
