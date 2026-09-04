<?php

namespace Modules\Payment\Entities;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentConfiguration extends Model
{
    use HasFactory, LogsAuditTrail;
    protected $table = "payment_configurations";
    protected $fillable = ['client_id', 'api_key', 'checksum_key',];

    // Không ghi giá trị THẬT của khoá bí mật PayOS vào audit_logs (chỉ ghi nhận CÓ thay đổi hay
    // không, giống cách 'password' bị loại ở LogsAuditTrail mặc định) — tránh lộ credential thanh
    // toán qua 1 bảng phụ ít được để ý bảo vệ hơn bảng gốc. KHÔNG gọi parent:: — auditExcludedFields()
    // mặc định nằm trong trait LogsAuditTrail (được "trộn" thẳng vào class này), không phải ở class
    // cha thật (Illuminate\Database\Eloquent\Model), nên parent:: sẽ báo lỗi "undefined method".
    protected static function auditExcludedFields(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'api_key', 'checksum_key'];
    }
}
