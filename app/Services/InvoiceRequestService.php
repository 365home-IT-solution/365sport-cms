<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\InvoiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;

// Xử lý yêu cầu hoá đơn VAT dùng chung cho API quản trị và màn Filament. Chỉ xử lý yêu cầu đang chờ.
class InvoiceRequestService
{
    public function issue(InvoiceRequest $req, string $number, UploadedFile|string|null $file, ?User $by): array
    {
        if ($req->status !== 'pending') {
            return ['ok' => false, 'message' => 'This request has already been handled.'];
        }

        $path = $file instanceof UploadedFile ? $file->store('invoices', 'public') : $file;
        $req->update(['status' => 'issued', 'invoice_number' => $number, 'invoice_file' => $path, 'issued_at' => now(), 'reject_reason' => null, 'handled_by' => $by?->id]);
        $this->notify($req, 'Hoá đơn VAT đã sẵn sàng', "Hoá đơn số {$number} cho đơn #{$req->order?->order_code} đã được xuất.");

        return ['ok' => true];
    }

    public function reject(InvoiceRequest $req, string $reason, ?User $by): array
    {
        if ($req->status !== 'pending') {
            return ['ok' => false, 'message' => 'This request has already been handled.'];
        }

        $req->update(['status' => 'rejected', 'reject_reason' => $reason, 'handled_by' => $by?->id]);
        $this->notify($req, 'Yêu cầu hoá đơn bị từ chối', "Đơn #{$req->order?->order_code}: {$reason}");

        return ['ok' => true];
    }

    private function notify(InvoiceRequest $req, string $title, string $body): void
    {
        if (! $req->customer_id || ! ($customer = Customer::find($req->customer_id))) {
            return;
        }

        try {
            app(NotificationFcmService::class)->sendToCustomer($customer, $title, $body, 'booking', ['subtype' => 'invoice', 'order_code' => (string) $req->order?->order_code]);
        } catch (\Throwable) {
            // Lỗi thông báo không được làm hỏng việc đã xử lý.
        }
    }
}
