<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

// MEGAMENU ADMIN theo NGHIỆP VỤ (cùng dạng với homestay): ít nhóm cấp 1 trên thanh trên, mỗi nhóm mở ra dropdown NHIỀU CỘT có tiêu đề.
// Đây là nơi DUY NHẤT quyết định mục nào nằm nhóm nào, cột nào, thứ tự nào — Resource/Page không cần khai báo $navigationGroup/
// $navigationSort nữa (có khai báo cũng bị bỏ qua). Quyền xem vẫn do chính Resource/Page quyết định (canAccess/canViewAny): mục không
// có quyền không hiện, cột/nhóm rỗng tự ẩn. Mục CHƯA được xếp (Resource mới thêm sau này) rơi vào cột "Khác" của nhóm "Cấu hình"
// để không bao giờ bị mất. View vẽ dropdown: resources/views/vendor/filament-panels/components/topbar/index.blade.php (lấy cột qua
// AdminNavigation::columnMap()).
//
// Khoá mục = tên class (không namespace), hoặc FQCN khi trùng tên (MediaResource/FolderResource của plugin media manager).
class AdminNavigation
{
    /**
     * Nhóm cấp 1 => cột => danh sách mục. Thứ tự khai báo = thứ tự hiển thị.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const MENU = [
        // Vận hành hằng ngày của chủ sân: đơn -> sân -> giá -> thi đấu -> kho -> camera -> đối tác (theo thứ tự công việc).
        'Quản lý' => [
            'Đặt sân & Đơn hàng' => [
                'OrderResource', 'PackageOrderResource', 'PendingRefunds', 'InvoiceRequestResource', 'CccdDeclarationResource', 'RoomHousekeepingResource',
            ],
            'Sân & Chi nhánh' => [
                'CategoryResource', 'ProductResource', 'RoomTypeResource', 'RoomImageResource', 'RoomAmenityResource',
                'RoomAmenityAssignResource', 'RoomServiceResource', 'RoomSpecialResource', 'AdditionServiceResource',
            ],
            'Giá & Khuyến mãi' => [
                'BookResource', 'PriceBoardResource', 'PromotionResource', 'CouponResource', 'PackagePlanResource', 'CustomerPackageResource',
            ],
            'Thi đấu & Cộng đồng' => [
                'OpenMatchResource', 'TeamResource', 'TournamentResource', 'BookingFundResource', 'CoachResource',
            ],
            'Kho & Dụng cụ' => [
                'WarehouseItemResource', 'WarehouseCategoryResource', 'WarehouseUnitResource', 'WarehouseStockInResource',
                'WarehouseStockOutResource', 'WarehouseStockCheckResource', 'WarehouseItemRentalResource',
            ],
            'Camera an ninh' => ['CameraMonitor', 'CameraResource', 'CameraAlertResource'],
            'Đối tác & Gói dịch vụ' => [
                'MySubscription', 'PartnerResource', 'PartnerLeadResource', 'PartnerPlanResource', 'PartnerFeatureMatrix', 'PartnerSettlementResource',
                'SubscriptionPaymentResource',
            ],
        ],

        // Cấu hình hệ thống + giao diện/nội dung website.
        'Cấu hình web' => [
            'Cấu hình chung' => [
                'ManageGeneral', 'SettingCompanyResource', 'BranchResource', 'ProvinceResource',
            ],
            'Thanh toán & Tích hợp' => [
                'PaymentSetup', 'PaymentConfigurationResource', 'ManageMail', 'GoogleLoginSettings',
                'DirectionsSettings', 'ManageCamera',
            ],
            'Giao diện website' => [
                'PageResource', 'MenuResource', 'ComponentResource', 'ThemeResource', 'ThemeStudioResource',
                'TomatoPHP\\FilamentMediaManager\\Resources\\FolderResource', 'TomatoPHP\\FilamentMediaManager\\Resources\\MediaResource',
            ],
            'Nội dung website' => [
                'PostResource', 'PostCategoryResource', 'TagResource', 'QAResource', 'ProcessResource',
            ],
            'Biểu mẫu & Phản hồi' => [
                'FormResource', 'FormSubmissionResource', 'FormNotificationResource', 'EmailSettingResource',
                'CommentResource', 'CommentPostResource', 'CommentProductResource', 'RoomRatingResource',
            ],
        ],

        // Con người: khách hàng, nhân viên, vai trò, lương, nhật ký.
        'Phân quyền' => [
            'Khách hàng' => [
                'CustomerResource', 'PlayerSkillLevelResource', 'GuestCustomerResource', 'MembershipTierResource', 'LoyaltyTransactionResource', 'CorporateAccountResource',
                'CustomerChat', 'ConsultationLogResource',
            ],
            'Tài khoản & Vai trò' => [
                'UserResource', 'RoleResource', 'UserBranchPermissionResource', 'DataScopeResource',
            ],
            'Nhân sự & Lương' => [
                'DepartmentResource', 'PositionResource', 'SalaryTypeResource', 'AllowanceTypeResource',
                'DeductionTypeResource', 'SalaryTemplateResource', 'EmployeeAttendanceResource', 'PayrollPeriodResource', 'SalaryReport',
            ],
            'Nhật ký & Tài liệu' => ['AuditLogResource', 'ExceptionResource', 'DocumentationResource'],
        ],

        // Nội dung đẩy xuống ứng dụng di động.
        'Quản lý API' => [
            'Nội dung ứng dụng' => ['AppPageResource', 'BannerResource', 'ManagePopups', 'AskUserResource'],
            'Thông báo' => ['NotificationFcmResource', 'ZnsNotificationResource'],
        ],
    ];

    /** Đổi tên hiển thị ở menu cho các mục tên gốc khó hiểu/không thống nhất (không đổi tiêu đề trang). */
    public const LABELS = [
        'OrderResource' => 'Đơn đặt sân',
        'ProductResource' => 'Sân',
        'RoomTypeResource' => 'Loại sân (môn thể thao)',
        'PromotionResource' => 'Khuyến mãi',
        'UserResource' => 'Tài khoản nhân viên',
        'TagResource' => 'Thẻ (Tag)',
        'ComponentResource' => 'Khối giao diện',
        'FormResource' => 'Biểu mẫu',
        'FormSubmissionResource' => 'Phản hồi biểu mẫu',
        'FormNotificationResource' => 'Thông báo biểu mẫu',
        'EmailSettingResource' => 'Email biểu mẫu',
        'CommentResource' => 'Thống kê bình luận',
        'QAResource' => 'Hỏi đáp (Q&A)',
        'NotificationFcmResource' => 'Thông báo đẩy',
        'AskUserResource' => 'Thông báo khi vào app',
        'ManagePopups' => 'Popup',
        'BranchResource' => 'Chi nhánh công ty',
        'ManageGeneral' => 'Cấu hình chung',
        'ManageMail' => 'Cấu hình email',
        'PaymentConfigurationResource' => 'Cấu hình thanh toán',
    ];

    /** @var array<string, array<string, list<string>>> nhóm cấp 1 => cột => nhãn mục (sau đổi tên), điền khi build() */
    private static array $columns = [];

    /**
     * Tuỳ chọn cho ô "Lọc theo module" ở màn phân quyền: cùng các nhóm/cột như megamenu (dạng optgroup).
     * Giá trị: 'grp:Nhóm' = cả nhóm cấp 1, 'col:Nhóm|Cột' = một cột, 'other' = mục chưa xếp vào menu.
     *
     * @return array<string, array<string, string>>
     */
    public static function moduleFilterOptions(): array
    {
        $options = [];
        foreach (self::MENU as $group => $columns) {
            $options[$group] = ['grp:' . $group => "Tất cả mục \"{$group}\""];
            foreach (array_keys($columns) as $column) {
                $options[$group]['col:' . $group . '|' . $column] = $column;
            }
        }
        $options['Khác'] = ['other' => 'Chưa xếp vào menu'];

        return $options;
    }

    /** Nhóm + cột megamenu của một class Resource/Page (null nếu chưa được xếp vào menu). */
    public static function moduleOf(string $fqcn): ?array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::MENU as $group => $columns) {
                foreach ($columns as $column => $keys) {
                    foreach ($keys as $key) {
                        $map[$key] = [$group, $column];
                    }
                }
            }
        }

        return $map[$fqcn] ?? $map[class_basename($fqcn)] ?? null;
    }

    /** Class có thuộc lựa chọn lọc module không (lựa chọn rỗng = tất cả). */
    public static function matchesModuleFilter(?string $filter, string $fqcn): bool
    {
        if ($filter === null || $filter === '') {
            return true;
        }

        $module = self::moduleOf($fqcn);

        return match (true) {
            $filter === 'other' => $module === null,
            str_starts_with($filter, 'grp:') => $module !== null && $module[0] === substr($filter, 4),
            str_starts_with($filter, 'col:') => $module !== null && ($module[0] . '|' . $module[1]) === substr($filter, 4),
            default => true,
        };
    }

    /** Cột (tiêu đề => nhãn mục) của một nhóm cấp 1 — view megamenu dùng để chia dropdown thành nhiều cột. */
    public static function columnMap(string $groupLabel): array
    {
        return self::$columns[$groupLabel] ?? [];
    }

    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        self::$columns = [];
        $panel = Filament::getCurrentPanel();

        /** @var array<string, class-string> $classes */
        $classes = [];
        foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
            $classes[$class] = $class;
        }

        $lookup = function (string $key) use ($classes): ?string {
            if (isset($classes[$key])) {
                return $key;
            }
            foreach ($classes as $class) {
                if (class_basename($class) === $key) {
                    return $class;
                }
            }

            return null;
        };

        $used = [];
        $itemsOf = function (string $class) use (&$used): array {
            $used[$class] = true;

            // Resource/Page tự khai báo ẩn khỏi menu (shouldRegisterNavigation = false) thì tôn trọng.
            if (method_exists($class, 'shouldRegisterNavigation') && ! $class::shouldRegisterNavigation()) {
                return [];
            }

            // Giống NavigationManager của Filament: tài khoản không có quyền truy cập thì không đưa mục vào menu.
            if (method_exists($class, 'canAccess') && ! $class::canAccess()) {
                return [];
            }

            return array_map(function (NavigationItem $item) use ($class) {
                $label = self::LABELS[class_basename($class)] ?? null;

                return $label ? $item->label($label) : $item;
            }, $class::getNavigationItems());
        };

        // Bảng điều khiển luôn đứng đầu, không thuộc nhóm nào.
        $dashboards = [];
        foreach ($classes as $class) {
            if (class_basename($class) === 'Dashboard') {
                $dashboards = [...$dashboards, ...$itemsOf($class)];
            }
        }
        $builder->items($dashboards);

        $groups = [];
        foreach (self::MENU as $groupLabel => $columns) {
            $items = [];
            foreach ($columns as $columnLabel => $keys) {
                foreach ($keys as $key) {
                    if (! ($class = $lookup($key))) {
                        continue;
                    }
                    foreach ($itemsOf($class) as $item) {
                        $items[] = $item;
                        self::$columns[$groupLabel][$columnLabel][] = $item->getLabel();
                    }
                }
            }

            // Mục chưa được xếp vào đâu (Resource mới thêm sau) -> cột "Khác" của nhóm cuối; mục do panel đăng ký tay cũng vào đây.
            if ($groupLabel === array_key_last(self::MENU)) {
                foreach ($classes as $class) {
                    if (! isset($used[$class]) && class_basename($class) !== 'Dashboard' && method_exists($class, 'getNavigationItems')) {
                        foreach ($itemsOf($class) as $item) {
                            $items[] = $item;
                            self::$columns[$groupLabel]['Khác'][] = $item->getLabel();
                        }
                    }
                }
                foreach ($panel->getNavigationItems() as $custom) {
                    $items[] = $custom;
                    self::$columns[$groupLabel]['Khác'][] = $custom->getLabel();
                }
            }

            // Nhóm không còn mục nào hiển thị được cho tài khoản này thì bỏ hẳn.
            $items = array_values(array_filter($items, fn (NavigationItem $i) => $i->isVisible()));
            if ($items !== []) {
                $groups[] = NavigationGroup::make($groupLabel)->items($items);
            }
        }

        return $builder->groups($groups);
    }
}
