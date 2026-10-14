<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvoiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Entities\Order;

// Khách yêu cầu hoá đơn VAT cho đơn đã thanh toán của chính mình.
class InvoiceRequestController extends Controller
{
    // GET /api/invoices — các yêu cầu hoá đơn của tôi
    public function index(): JsonResponse
    {
        $rows = InvoiceRequest::query()->with('order:id,order_code')->where('customer_id', auth('sanctum')->id())->orderByDesc('id')->get();

        return response()->json(['data' => $rows->map(fn (InvoiceRequest $r) => $this->item($r))->all()]);
    }

    // GET /api/orders/{order_code}/invoice
    public function show(string $orderCode): JsonResponse
    {
        $order = $this->myOrder($orderCode);
        $req = $order ? InvoiceRequest::query()->with('order:id,order_code')->where('order_id', $order->id)->first() : null;

        return $req ? response()->json(['data' => $this->item($req)]) : response()->json(['message' => 'No invoice request for this order.'], 404);
    }

    // POST /api/orders/{order_code}/invoice — body: company_name, tax_code, address, email, note?
    public function store(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->myOrder($orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        if (! in_array($order->status, ['paid', 'completed'], true)) {
            return response()->json(['message' => 'An invoice can only be requested for a paid order.'], 422);
        }

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'tax_code' => ['required', 'string', 'regex:/^\d{10}(-?\d{3})?$/'],
            'address' => 'required|string|max:500',
            'email' => 'required|email|max:255',
            'note' => 'nullable|string|max:500',
        ], ['tax_code.regex' => 'Mã số thuế phải gồm 10 hoặc 13 chữ số.']);

        $existing = InvoiceRequest::query()->where('order_id', $order->id)->first();
        if ($existing && $existing->status !== 'rejected') {
            return response()->json(['message' => 'This order already has an invoice request.', 'data' => $this->item($existing->load('order:id,order_code'))], 409);
        }

        // Yêu cầu bị từ chối được gửi lại (cập nhật thông tin và quay về chờ xử lý).
        $payload = array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $data) + ['customer_id' => $order->customer_id, 'partner_id' => $order->partner_id, 'status' => 'pending', 'reject_reason' => null];
        $req = $existing ? tap($existing)->update($payload) : InvoiceRequest::create($payload + ['order_id' => $order->id]);

        return response()->json(['message' => 'Invoice request submitted.', 'data' => $this->item($req->load('order:id,order_code'))], 201);
    }

    private function myOrder(string $orderCode): ?Order
    {
        return Order::query()->where('order_code', $orderCode)->where('customer_id', auth('sanctum')->id())->first();
    }

    private function item(InvoiceRequest $r): array
    {
        return [
            'order_code' => $r->order?->order_code, 'company_name' => $r->company_name, 'tax_code' => $r->tax_code, 'address' => $r->address, 'email' => $r->email,
            'note' => $r->note, 'status' => $r->status, 'invoice_number' => $r->invoice_number, 'invoice_file_url' => $r->invoice_file_url,
            'issued_at' => $r->issued_at?->toIso8601String(), 'reject_reason' => $r->reject_reason, 'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
