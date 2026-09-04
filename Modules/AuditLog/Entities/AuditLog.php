<?php

declare(strict_types=1);

namespace Modules\AuditLog\Entities;

use App\Models\Concerns\BelongsToPartner;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToPartner;

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'partner_id',
        'user_id',
        'user_name',
        'user_email',
        'performer_role',
        'action',
        'module',
        'target_id',
        'target_label',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Nhãn hiển thị cho module
    public static function moduleLabels(): array
    {
        return [
            'Role'                 => 'Vai trò',
            'User'                 => 'Nhân viên',
            'UserBranchPermission' => 'Phân quyền chi nhánh',
            'Post'                 => 'Bài viết',
            'Product'              => 'Sân',
            'Order'                => 'Booking',
            'Branch'               => 'Chi nhánh',
            'Coupon'               => 'Mã giảm giá',
            'Promotion'            => 'Khuyến mãi',
            // Gắn thêm qua trait LogsAuditTrail (app/Models/Concerns/LogsAuditTrail.php) — port từ
            // 365home-cms, không cần Observer viết tay như 9 model phía trên.
            'AskUser'              => 'Hỏi người dùng',
            'CccdDeclaration'      => 'Khai báo lưu trú',
            'GuestCustomer'        => 'Khách vãng lai',
            'NotificationFcm'      => 'Thông báo đẩy',
            'Tag'                  => 'Thẻ gắn nhãn',
            'AppPage'              => 'Trang app',
            'Banner'               => 'Banner',
            'PopupImage'           => 'Popup quảng cáo',
            'Category'             => 'Danh mục / Chi nhánh',
            'DataScope'            => 'Phạm vi dữ liệu',
            'Business'             => 'Thông tin công ty',
            'RoomType'             => 'Loại sân',
            'RoomImage'            => 'Ảnh sân',
            'RoomService'          => 'Dịch vụ sân',
            'RoomSpecial'          => 'Đặc điểm nổi bật',
            'WarehouseItem'        => 'Vật tư kho',
            'WarehouseStockCheck'  => 'Phiếu kiểm kê kho',
            'WarehouseStockIn'     => 'Phiếu nhập kho',
            'WarehouseStockOut'    => 'Phiếu xuất kho',
            'RoomTimeSlot'         => 'Khung giờ / Giá sân',
            'PriceBoard'           => 'Bảng giá',
            // Mở rộng audit trail phủ toàn bộ module admin (trước đây chỉ ~20 model có log) — vẫn
            // qua trait LogsAuditTrail, không đụng 9 model Observer cũ phía trên.
            'Comment'              => 'Bình luận',
            'ZnsNotification'      => 'Thông báo ZNS',
            'BookingFund'          => 'Quỹ nhóm góp tiền',
            'MembershipTier'       => 'Hạng thành viên',
            'Province'             => 'Tỉnh/Thành phố',
            'CustomerPackage'      => 'Khách đã mua gói',
            'FormSubmission'       => 'Biểu mẫu đến',
            'ConsultationLog'      => 'Lượt tư vấn khách hàng',
            'PackagePlan'          => 'Gói nhiều buổi',
            'Partner'              => 'Đối tác',
            'PartnerPlan'          => 'Gói đối tác',
            'Process'              => 'Quy trình',
            'Customer'             => 'Khách hàng',
            'Employee'             => 'Hồ sơ nhân viên',
            'SalaryType'           => 'Loại lương',
            'SalaryTemplate'       => 'Mẫu lương',
            'DeductionType'        => 'Loại giảm trừ',
            'Position'             => 'Chức danh',
            'AllowanceType'        => 'Loại phụ cấp',
            'Department'           => 'Phòng ban',
            'PaymentConfiguration' => 'Cấu hình thanh toán',
            'Page'                 => 'Trang',
            'Theme'                => 'Theme',
            'QA'                   => 'Hỏi đáp',
            'FormNotification'     => 'Thông báo biểu mẫu',
            'EmailSetting'         => 'Cấu hình email biểu mẫu',
            'AdditionService'      => 'Dịch vụ bổ sung',
            'RoomAmenity'          => 'Tiện ích sân',
            'WarehouseUnit'        => 'Đơn vị tính kho',
            'WarehouseCategory'    => 'Nhóm vật tư',
        ];
    }

    // Nhãn hiển thị cho action
    public static function actionLabels(): array
    {
        return [
            'create' => 'Tạo mới',
            'update' => 'Cập nhật',
            'delete' => 'Xóa',
        ];
    }

    public function getModuleLabelAttribute(): string
    {
        return static::moduleLabels()[$this->module] ?? $this->module;
    }

    public function getActionLabelAttribute(): string
    {
        return static::actionLabels()[$this->action] ?? $this->action;
    }
}
