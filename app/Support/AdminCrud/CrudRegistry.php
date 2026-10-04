<?php

declare(strict_types=1);

namespace App\Support\AdminCrud;

use App\Models\AskUser;
use App\Models\CccdDeclaration;
use App\Models\Customer;
use App\Models\GuestCustomer;
use App\Models\MembershipTier;
use App\Models\OpenMatch;
use App\Models\Partner;
use App\Models\PartnerPlan;
use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\AppPage\App\Models\AppPage;
use Modules\AppPage\App\Models\Banner;
use Modules\AuditLog\Entities\AuditLog;
use Modules\BladeThemeV1\App\Models\AdditionService;
use Modules\Comment\Entities\Comment;
use Modules\DataPermission\Entities\DataScope;
use Modules\DataPermission\Entities\UserBranchPermission;
use Modules\Employee\Entities\ConsultationLog;
use Modules\Form\Entities\EmailSetting;
use Modules\Form\Entities\Form;
use Modules\Form\Entities\FormNotification;
use Modules\Form\Entities\FormSubmission;
use Modules\Page\Entities\Component;
use Modules\Page\Entities\Page;
use Modules\Payment\Entities\PaymentConfiguration;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Product\App\Models\RoomType;
use Modules\QA\App\Models\QA;
use Modules\SettingCompany\Entities\Branch;
use Modules\SettingCompany\Entities\Business;
use Modules\Zns\App\Models\ZnsNotification;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;

// Khai báo các nhóm route CRUD của API admin do GenericCrudController phục vụ. Khoá = đoạn URL sau /api/admin/.
//
// Mỗi khai báo:
//   model        — Eloquent model (bắt buộc)
//   permission   — tên quyền Shield (vd 'cccd::declaration' → view_any_/create_/update_/delete_cccd::declaration).
//                  null = CHỈ super_admin, giống các màn Filament tự giới hạn cho super admin.
//   ops          — index, show, store, update, destroy (mặc định đủ 5; bỏ bớt cho màn chỉ xem)
//   search       — các cột tìm kiếm LIKE theo ?search=
//   filters      — ?param → cột (hoặc danh sách cột trùng tên)
//   with / sort / transform / scope / scopes / before / after / deletable / files / rules / except / required / optional / fields
//                — xem GenericCrudController. Luật validate mặc định được sinh từ $fillable + cấu trúc bảng.
final class CrudRegistry
{
    public const ALL_OPS = ['index', 'show', 'store', 'update', 'destroy'];

    /** @return array<string, array> */
    public static function all(): array
    {
        static $defs = null;

        return $defs ??= self::build();
    }

    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** Có khai báo cho nhóm route này không (kể cả khai báo chỉ super admin). */
    public static function has(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    public static function permission(string $slug): ?string
    {
        return self::all()[$slug]['permission'] ?? null;
    }

    public static function ops(string $slug): array
    {
        return self::all()[$slug]['ops'] ?? self::ALL_OPS;
    }

    // ── Bộ lọc phạm vi dùng lại ─────────────────────────────────────────────────────────────

    /** Giới hạn theo chi nhánh mà người dùng được xem (giống getEloquentQuery của các màn Filament). */
    private static function branchColumn(string $column): \Closure
    {
        return function (Builder $q, $user) use ($column) {
            $ids = $user instanceof User ? ($user->effectiveBranchIds() ?? []) : [];
            if ($ids) {
                $q->whereIn($column, $ids);
            }
        };
    }

    private static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_repeat('•', 8) . substr($value, -4);
    }

    private static function build(): array
    {
        return [
            // ── Khách hàng ──────────────────────────────────────────────────────────────────
            'cccd-declarations' => [
                'model' => CccdDeclaration::class, 'permission' => 'cccd::declaration',
                'ops' => ['index', 'show', 'update', 'destroy'],
                'search' => ['full_name', 'cccd_number', 'phone_number'], 'filters' => ['order_id'],
                'scope' => function (Builder $q, $user) {
                    $ids = $user instanceof User ? ($user->effectiveBranchIds() ?? []) : [];
                    $q->whereHas('order', fn ($o) => $ids ? $o->whereIn('orders.category_id', $ids) : $o);
                },
            ],
            'guest-customers' => [
                'model' => GuestCustomer::class, 'permission' => 'guest::customer',
                'ops' => ['index', 'show'], 'search' => ['fullname', 'phone'], 'filters' => ['province_id'],
            ],
            'membership-tiers' => [
                'model' => MembershipTier::class, 'permission' => 'membership::tier',
                'search' => ['name'], 'filters' => ['is_active'], 'sort' => ['sort_order', 'asc'],
                'deletable' => fn (Model $m) => Customer::where('membership_tier_id', $m->getKey())->exists()
                    ? 'Customers are assigned to this tier; move them to another tier first.' : null,
            ],

            // ── Chi nhánh, sân, loại sân ────────────────────────────────────────────────────
            'addition-services' => [
                'model' => AdditionService::class, 'permission' => 'addition::service',
                'search' => ['name'], 'filters' => ['is_active'], 'files' => ['image' => 'addition-services'],
            ],
            'room-types' => [
                'model' => RoomType::class, 'permission' => 'room::type',
                'ops' => ['show', 'store', 'update', 'destroy'], // danh sách đã có ở RoomTypeController
                'deletable' => fn (Model $m) => Product::where('room_type_id', $m->getKey())->exists()
                    ? 'Courts still use this court type.' : null,
            ],
            'branches' => [
                'model' => Branch::class, 'permission' => 'branch',
                'ops' => ['show', 'store', 'update', 'destroy'], // danh sách đã có ở BranchController
            ],
            'businesses' => [
                'model' => Business::class, 'permission' => 'setting::company',
                'search' => ['name', 'phone', 'email'], 'files' => ['image' => 'businesses'],
            ],
            'provinces' => [
                'model' => Province::class, 'permission' => 'province',
                'search' => ['name', 'slug', 'code'], 'files' => ['image' => 'provinces'], 'sort' => ['name', 'asc'],
                'deletable' => fn (Model $m) => $m->branches()->exists() ? 'This province still has branches.' : null,
            ],
            'room-time-slots' => [
                'model' => RoomTimeSlot::class, 'permission' => 'book',
                'ops' => ['index', 'show', 'destroy'], 'filters' => ['room_id', 'status', 'date'],
                'scope' => function (Builder $q, $user) {
                    if ($user instanceof User && ! $user->isSuperAdmin()) {
                        $q->whereIn('room_id', Product::query()->where('partner_id', $user->partner_id)->select('id'));
                    }
                },
            ],

            // ── Thi đấu & cộng đồng (chỉ super admin xem, giống Filament) ───────────────────
            'open-matches' => [
                'model' => OpenMatch::class, 'permission' => null, 'ops' => ['index', 'show'],
                'filters' => ['status', 'category_id'], 'with' => ['host:id,fullname,phone'],
            ],
            'teams' => [
                'model' => \App\Models\Team::class, 'permission' => null, 'ops' => ['index', 'show'],
                'search' => ['name', 'code'], 'filters' => ['status'], 'with' => ['leader:id,fullname,phone'],
                'transform' => fn (Model $m) => $m->toArray() + ['member_count' => $m->memberCount()],
            ],
            'booking-funds' => [
                'model' => \App\Models\BookingFund::class, 'permission' => null, 'ops' => ['index', 'show'],
                'search' => ['title', 'invite_code'], 'filters' => ['status', 'team_id'], 'with' => ['leader:id,fullname,phone', 'team:id,name,code'],
                'except' => ['bank_account_number'],
                'transform' => fn (Model $m) => $m->toArray() + ['collected_amount' => $m->collectedAmount(), 'progress_percent' => $m->progressPercent()],
            ],

            // ── Đánh giá & bình luận ────────────────────────────────────────────────────────
            'comments' => [
                'model' => Comment::class, 'permission' => 'comment',
                'search' => ['name', 'text'], 'filters' => ['show', 'pin', 'commentable_type'],
            ],

            // ── Đối tác & gói dịch vụ (chỉ super admin) ─────────────────────────────────────
            'partners' => [
                'model' => Partner::class, 'permission' => null,
                'search' => ['name', 'slug', 'phone', 'email', 'tax_code'], 'filters' => ['status', 'verification_status', 'partner_plan_id'],
                'except' => ['onboarding_token', 'created_by', 'payos_api_key', 'payos_checksum_key'],
                'transform' => fn (Model $m) => array_merge($m->toArray(), [
                    'payos_api_key' => self::mask($m->payos_api_key), 'payos_checksum_key' => self::mask($m->payos_checksum_key),
                    'onboarding_token' => null,
                ]),
            ],
            'partner-plans' => [
                'model' => PartnerPlan::class, 'permission' => null,
                'search' => ['name', 'slug'], 'filters' => ['is_active'], 'sort' => ['sort_order', 'asc'],
                'deletable' => fn (Model $m) => Partner::where('partner_plan_id', $m->getKey())->exists()
                    ? 'Partners are on this plan; move them to another plan first.' : null,
            ],

            // ── Phân quyền & nhật ký ────────────────────────────────────────────────────────
            'data-scopes' => [
                'model' => DataScope::class, 'permission' => 'data::scope', 'filters' => ['user_id', 'table_name'],
            ],
            'user-branch-permissions' => [
                'model' => UserBranchPermission::class, 'permission' => 'user::branch::permission',
                'ops' => ['index', 'store', 'destroy'], 'filters' => ['user_id', 'category_id'],
                'except' => ['created_by'],
                'before' => function (array $data, ?Model $m, $request) {
                    $data['created_by'] = $request->user()->getKey();

                    return $data;
                },
            ],
            'audit-logs' => [
                'model' => AuditLog::class, 'permission' => 'audit::log', 'ops' => ['index', 'show'],
                'search' => ['user_name', 'user_email', 'target_label'], 'filters' => ['module', 'action', 'user_id'], 'sort' => ['id', 'desc'],
            ],

            // ── Nội dung app & thông báo ────────────────────────────────────────────────────
            'app-pages' => [
                'model' => AppPage::class, 'permission' => 'app::page', 'search' => ['name', 'slug'], 'filters' => ['is_active'],
            ],
            'banners' => [
                'model' => Banner::class, 'permission' => 'banner', 'search' => ['title'], 'filters' => ['is_active'],
                'files' => ['image' => 'banners'], 'sort' => ['sort_order', 'asc'], 'optional' => ['image'],
                'rules' => ['image' => fn (bool $partial) => $partial ? ['sometimes', 'file', 'image', 'max:8192'] : ['required', 'file', 'image', 'max:8192']],
            ],
            'ask-users' => [
                'model' => AskUser::class, 'permission' => 'ask::user', 'search' => ['title'],
            ],
            'zns-notifications' => [
                'model' => ZnsNotification::class, 'permission' => 'zns::notification',
                'search' => ['phone_number', 'recipient_name', 'template_name'], 'filters' => ['status', 'order_id'],
            ],

            // ── Nội dung website ────────────────────────────────────────────────────────────
            'qas' => ['model' => QA::class, 'permission' => 'q::a', 'search' => ['name', 'slug'], 'filters' => ['status']],
            'components' => ['model' => Component::class, 'permission' => 'component', 'search' => ['name', 'label']],
            'forms' => ['model' => Form::class, 'permission' => 'form', 'search' => ['name']],
            'form-submissions' => [
                'model' => FormSubmission::class, 'permission' => 'form::submission',
                'ops' => ['index', 'show', 'update', 'destroy'], 'filters' => ['form_id', 'is_viewed'],
            ],
            'form-notifications' => ['model' => FormNotification::class, 'permission' => 'form::notification', 'filters' => ['form_id']],
            'email-settings' => ['model' => EmailSetting::class, 'permission' => 'email::setting', 'filters' => ['form_id'], 'search' => ['to_email', 'subject']],
            'media' => ['model' => Media::class, 'permission' => 'media', 'ops' => ['index', 'show', 'destroy'], 'search' => ['name', 'file_name'], 'filters' => ['collection_name', 'model_type']],
            'folders' => ['model' => Folder::class, 'permission' => 'folder', 'search' => ['name']],
        ];
    }
}
