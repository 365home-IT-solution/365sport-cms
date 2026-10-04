<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiAuth
{
    /**
     * Map API URL groups to the same Shield resource names used by Filament.
     * Keeping this in one place prevents an endpoint from being added without
     * an authorization decision: unknown groups are denied for non super-admins.
     */
    private const RESOURCES = [
        'branches' => 'branch', 'rooms' => 'product', 'bookings' => 'order',
        'orders' => 'order', 'customers' => 'customer', 'guest-customers' => 'guest::customer',
        'categories' => 'category', 'coupons' => 'coupon', 'promotions' => 'promotion',
        'products' => 'product', 'tags' => 'tag', 'services' => 'addition::service',
        'departments' => 'department', 'positions' => 'position', 'employees' => 'user',
        'salary-types' => 'salary::type', 'allowance-types' => 'allowance::type',
        'deduction-types' => 'deduction::type', 'salary-templates' => 'salary::template',
        'package-plans' => 'package::plan', 'package-orders' => 'order',
        'customer-packages' => 'order', 'corporate-accounts' => 'customer',
        'recurring-bookings' => 'order', 'price-boards' => 'product',
        'room-types' => 'room::type', 'room-amenities' => 'room::amenity',
        'room-images' => 'room::image', 'room-services' => 'room::service',
        'room-specials' => 'room::special', 'room-housekeeping' => 'room::housekeeping',
        'warehouse-categories' => 'warehouse::category', 'warehouse-items' => 'warehouse::item',
        'warehouse-units' => 'warehouse::unit', 'warehouse-stock-ins' => 'warehouse::stock::in',
        'warehouse-stock-outs' => 'warehouse::stock::out',
        'warehouse-stock-checks' => 'warehouse::stock::check',
        'warehouse-item-rentals' => 'warehouse::item',
        'chat' => 'page_CustomerChat',
        'dashboard' => 'page_Dashboard', 'reports' => 'page_Dashboard',
        'coaches' => 'product',
        'cameras' => 'product', 'camera-alerts' => 'product', 'camera-settings' => 'product',
        'time-slots' => 'product',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! ($user instanceof User)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Authentication/profile endpoints do not expose tenant business data.
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);
        $group = $segments[2] ?? '';
        if (in_array($group, ['logout', 'me'], true)) {
            return $next($request);
        }

        // Gói dịch vụ hết hạn → 402 (trừ nhóm luôn cho phép để đối tác xem & thanh toán gia hạn).
        if ($blocked = \App\Support\SubscriptionGate::checkRequest($request, $user)) {
            return $blocked;
        }

        // Gói dịch vụ của CHÍNH đối tác: controller tự giới hạn theo users.partner_id, không cần quyền Shield riêng.
        if ($group === 'subscription') {
            return $next($request);
        }

        // Filament chặn tính năng ngoài gói dịch vụ của đối tác (canViewAny → partnerHasFeature) —
        // API phải chặn tương đương, nếu không đối tác vẫn gọi được tính năng mình chưa mua.
        $feature = match (true) {
            in_array($group, ['cameras', 'camera-alerts', 'camera-settings'], true) => 'camera',
            $group === 'warehouse' => 'warehouse',
            default => null,
        };
        if ($feature !== null && ! $user->partnerHasFeature($feature)) {
            return response()->json(['message' => 'Forbidden: this feature is not included in your plan.'], 403);
        }

        $resource = $group === 'warehouse'
            ? match ($segments[3] ?? '') {
                'categories' => 'warehouse::category',
                'items', 'item-rentals' => 'warehouse::item',
                'units' => 'warehouse::unit',
                'stock-ins' => 'warehouse::stock::in',
                'stock-outs' => 'warehouse::stock::out',
                'stock-checks' => 'warehouse::stock::check',
                default => null,
            }
            : (self::RESOURCES[$group] ?? \App\Support\AdminCrud\CrudRegistry::permission($group));
        if ($resource === null) {
            return response()->json([
                'message' => 'Forbidden: this admin API has no configured permission.',
            ], 403);
        }

        $ability = match ($request->method()) {
            'GET', 'HEAD' => 'view_any_' . $resource,
            'POST' => 'create_' . $resource,
            'PUT', 'PATCH' => 'update_' . $resource,
            'DELETE' => 'delete_' . $resource,
            default => null,
        };

        // POST is also used for commands on existing records. Such commands
        // require update permission when create permission is not applicable.
        $allowed = $ability !== null && $user->can($ability);
        if (! $allowed && $request->isMethod('POST')) {
            $allowed = $user->can('update_' . $resource);
        }

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
