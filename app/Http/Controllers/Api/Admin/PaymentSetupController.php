<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\PaymentSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Thiết lập thanh toán của đối tác (API tương đương trang Filament "Thiết lập thanh toán"). Chỉ chủ đối tác; khoá PayOS không bao giờ trả về nguyên văn.
class PaymentSetupController extends Controller
{
    // GET /api/admin/payment-setup
    public function show(Request $request): JsonResponse
    {
        $partner = $this->partner($request);

        return $partner instanceof JsonResponse ? $partner : response()->json(['data' => app(PaymentSetupService::class)->current($partner)]);
    }

    // PUT|PATCH|POST /api/admin/payment-setup — body: payment_method_preference (payos|bank_transfer_sepay|bank_transfer_manual), bank_bin, bank_account_number,
    // bank_account_holder (KHÔNG dấu), bank_branch?, payos_client_id?, payos_api_key?, payos_checksum_key? (để trống = giữ khoá cũ)
    public function update(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        if ($partner instanceof JsonResponse) {
            return $partner;
        }

        $saved = app(PaymentSetupService::class)->save($partner, $request->all());

        return response()->json(['message' => 'Payment setup saved.', 'data' => app(PaymentSetupService::class)->current($saved)]);
    }

    private function partner(Request $request): Partner|JsonResponse
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || ! $user->partner_id || ! $user->isPartnerOwner()) {
            return response()->json(['message' => 'Only a partner owner can manage payment setup.'], 403);
        }

        return Partner::query()->findOrFail($user->partner_id);
    }
}
