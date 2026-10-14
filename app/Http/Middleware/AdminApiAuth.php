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
        'refunds' => 'page_PendingRefunds',
        'attendance' => 'employee::attendance', 'payroll' => 'payroll::period', 'partner-settlements' => 'partner::settlement', 'transactions' => 'order', 'users' => 'user', 'roles' => 'shield::role', 'fcm-notifications' => 'notification::fcm', 'ratings' => 'room::rating', 'loyalty' => 'loyalty::transaction', 'invoice-requests' => 'invoice::request', 'tournaments' => 'tournament', 'player-skills' => 'player::skill::level', 'payment-setup' => 'page_PaymentSetup',
        'cameras' => 'product', 'camera-alerts' => 'product', 'camera-settings' => 'product',
        'time-slots' => 'product',
    ];

    /** Mỗi nhóm cấu hình tương ứng một trang Filament với quyền 'page_X' riêng. */
    private function settingsPermission(string $group): ?string
    {
        return match ($group) {
            'general' => 'page_ManageGeneral',
            'mail' => 'page_ManageMail',
            'google-login' => 'page_GoogleLoginSettings',
            'directions' => 'page_DirectionsSettings',
            default => null,
        };
    }

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

        // Nhân viên tự chấm công/xem công của mình: chỉ cần đăng nhập, controller tự tìm hồ sơ nhân sự gắn với tài khoản.
        if ($group === 'attendance' && in_array($segments[3] ?? '', ['me', 'check-in', 'check-out'], true)) {
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
            : (self::RESOURCES[$group] ?? ($group === 'settings' ? $this->settingsPermission($segments[3] ?? '') : \App\Support\AdminCrud\CrudRegistry::permission($group)));
        if ($resource === null) {
            return response()->json([
                'message' => 'Forbidden: this admin API has no configured permission.',
            ], 403);
        }

        // Màn hình dạng "trang" của Filament (Dashboard, Chat khách hàng, Hoàn tiền chờ xử lý...) chỉ có MỘT quyền duy nhất
        // tên 'page_X' (không có view_any_/create_/update_/delete_). Trước đây vẫn ghép 'view_any_page_X' — quyền không tồn
        // tại nên mọi tài khoản không phải super_admin đều bị 403 ở các API này.
        if (str_starts_with($resource, 'page_')) {
            return $user->can($resource)
                ? $next($request)
                : response()->json(['message' => 'Forbidden.'], 403);
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
