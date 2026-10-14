<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\FeatureRoutes;
use Closure;
use Illuminate\Http\Request;

// Chặn các API thuộc tính năng đang tắt trong config/features.php (đối soát hoa hồng, giải đấu, kèo, chấm công, tính lương).
class BlockDisabledFeatures
{
    public function handle(Request $request, Closure $next)
    {
        if (FeatureRoutes::disabledFlagFor($request->path()) !== null) {
            return response()->json(['message' => 'This feature is currently disabled.'], 404);
        }

        return $next($request);
    }
}
