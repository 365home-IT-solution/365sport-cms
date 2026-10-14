<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceRequest;
use App\Services\InvoiceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Đối tác xử lý yêu cầu hoá đơn VAT của đơn thuộc mình. Super admin thấy tất cả.
class InvoiceRequestAdminController extends Controller
{
    // GET /api/admin/invoice-requests — ?status=pending|issued|rejected, ?search= (mã đơn/MST/tên công ty), ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = $this->scoped($request)->with('order:id,order_code,full_amount')
            ->when(in_array($request->query('status'), ['pending', 'issued', 'rejected'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim((string) $request->query('search')) . '%';
                $q->where(fn ($w) => $w->where('tax_code', 'like', $s)->orWhere('company_name', 'like', $s)->orWhereHas('order', fn ($o) => $o->where('order_code', 'like', $s)));
            })
            ->orderByRaw("status = 'pending' desc")->orderByDesc('id')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $page->through(fn (InvoiceRequest $r) => $this->item($r));

        return response()->json($page);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $r = $this->scoped($request)->with('order:id,order_code,full_amount')->find($id);

        return $r ? response()->json(['data' => $this->item($r)]) : response()->json(['message' => 'Invoice request not found.'], 404);
    }

    // POST /api/admin/invoice-requests/{id}/issue — multipart: invoice_number, invoice_file? (pdf/xml/jpg/png ≤ 10MB)
    public function issue(Request $request, int $id): JsonResponse
    {
        $r = $this->scoped($request)->find($id);
        if (! $r) {
            return response()->json(['message' => 'Invoice request not found.'], 404);
        }

        $data = $request->validate(['invoice_number' => 'required|string|max:50', 'invoice_file' => 'nullable|file|mimes:pdf,xml,jpg,jpeg,png|max:10240']);
        $result = app(InvoiceRequestService::class)->issue($r, $data['invoice_number'], $request->file('invoice_file'), $request->user());

        return $result['ok'] ? response()->json(['data' => $this->item($r->fresh('order:id,order_code,full_amount'))]) : response()->json(['message' => $result['message']], 422);
    }

    // POST /api/admin/invoice-requests/{id}/reject — body: reason
    public function reject(Request $request, int $id): JsonResponse
    {
        $r = $this->scoped($request)->find($id);
        if (! $r) {
            return response()->json(['message' => 'Invoice request not found.'], 404);
        }

        $data = $request->validate(['reason' => 'required|string|max:500']);
        $result = app(InvoiceRequestService::class)->reject($r, $data['reason'], $request->user());

        return $result['ok'] ? response()->json(['data' => $this->item($r->fresh('order:id,order_code,full_amount'))]) : response()->json(['message' => $result['message']], 422);
    }

    private function scoped(Request $request)
    {
        $user = $request->user();
        $q = InvoiceRequest::query();

        return $user->isSuperAdmin() ? $q : $q->where('partner_id', $user->partner_id);
    }

    private function item(InvoiceRequest $r): array
    {
        return [
            'id' => $r->id, 'order_code' => $r->order?->order_code, 'order_amount' => (int) ($r->order?->full_amount ?? 0), 'company_name' => $r->company_name,
            'tax_code' => $r->tax_code, 'address' => $r->address, 'email' => $r->email, 'note' => $r->note, 'status' => $r->status,
            'invoice_number' => $r->invoice_number, 'invoice_file_url' => $r->invoice_file_url, 'issued_at' => $r->issued_at?->toIso8601String(),
            'reject_reason' => $r->reject_reason, 'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
