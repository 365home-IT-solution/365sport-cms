<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Thiết lập cách THU TIỀN của đối tác (ngân hàng / PayOS riêng) do chính chủ đối tác thực hiện — dùng chung cho trang Filament "Thiết lập thanh toán"
// và API quản trị. Đối tác chưa thiết lập xong thì sân không nhận đặt online và không bật bán sân được (xem Partner::hasUsablePaymentMethod()).
class PaymentSetupService
{
    public const METHODS = [
        'payos' => 'PayOS (cổng thanh toán online, cần khoá PayOS của bạn)',
        'bank_transfer_sepay' => 'Chuyển khoản — tự động xác nhận (SePay)',
        'bank_transfer_manual' => 'Chuyển khoản — xác nhận thủ công (duyệt ảnh biên lai)',
    ];

    /** Trạng thái hiện tại để hiển thị (khoá bí mật không bao giờ trả về nguyên văn). */
    public function current(Partner $p): array
    {
        return [
            'payment_method_preference' => $p->payment_method_preference ?: 'payos',
            'bank_bin' => $p->bank_bin,
            'bank_name' => $p->bank_name,
            'bank_branch' => $p->bank_branch,
            'bank_account_number' => $p->bank_account_number,
            'bank_account_holder' => $p->bank_account_holder,
            'payos_configured' => $p->hasPayosConfigured(),
            'payos_client_id_hint' => $p->payos_client_id ? '••••' . substr((string) $p->payos_client_id, -4) : null,
            'ready' => $p->hasUsablePaymentMethod(),
            'effective_method' => $p->effectivePaymentMethod(),
        ];
    }

    /**
     * Lưu thiết lập. Khoá PayOS để trống = giữ nguyên khoá cũ. Ném ValidationException nếu thiếu / sai.
     */
    public function save(Partner $p, array $input): Partner
    {
        $banks = array_keys((array) config('vietnam_banks'));
        $method = $input['payment_method_preference'] ?? ($p->payment_method_preference ?: 'payos');
        $isBank = in_array($method, ['bank_transfer_sepay', 'bank_transfer_manual'], true);

        $data = Validator::make($input, [
            'payment_method_preference' => ['required', Rule::in(array_keys(self::METHODS))],
            'bank_bin' => [$isBank ? 'required' : 'nullable', Rule::in($banks)],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => [$isBank ? 'required' : 'nullable', 'regex:/^[0-9]{6,20}$/'],
            'bank_account_holder' => [$isBank ? 'required' : 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9 .\'\-]+$/'],
            'payos_client_id' => ['nullable', 'string', 'max:255'],
            'payos_api_key' => ['nullable', 'string', 'max:255'],
            'payos_checksum_key' => ['nullable', 'string', 'max:255'],
        ], [
            'bank_bin.required' => 'Vui lòng chọn ngân hàng.',
            'bank_bin.in' => 'Ngân hàng không hợp lệ.',
            'bank_account_number.required' => 'Vui lòng nhập số tài khoản.',
            'bank_account_number.regex' => 'Số tài khoản gồm 6–20 chữ số.',
            'bank_account_holder.required' => 'Vui lòng nhập tên chủ tài khoản.',
            'bank_account_holder.regex' => 'Tên chủ tài khoản viết KHÔNG dấu, đúng như trên thẻ.',
        ])->validate();

        // Khoá PayOS: trường để trống thì giữ khoá cũ; khi chọn PayOS thì cần đủ cả 3 khoá (cũ hoặc mới) — trừ khi nền tảng cho dùng khoá chung.
        $keys = [];
        foreach (['payos_client_id', 'payos_api_key', 'payos_checksum_key'] as $k) {
            $keys[$k] = filled($data[$k] ?? null) ? trim((string) $data[$k]) : $p->{$k};
        }
        if ($method === 'payos' && ! $p->isPlatformPartner() && ! config('features.platform_payos_for_partners') && in_array(null, array_map(fn ($v) => filled($v) ? $v : null, $keys), true)) {
            throw ValidationException::withMessages(['payos_client_id' => 'Chọn PayOS thì cần nhập đủ Client ID, API Key và Checksum Key.']);
        }

        $p->forceFill([
            'payment_method_preference' => $method,
            'bank_bin' => filled($data['bank_bin'] ?? null) ? $data['bank_bin'] : $p->bank_bin,
            'bank_name' => ($data['bank_bin'] ?? null) ? (config('vietnam_banks')[$data['bank_bin']] ?? $p->bank_name) : $p->bank_name,
            'bank_branch' => $data['bank_branch'] ?? $p->bank_branch,
            'bank_account_number' => $data['bank_account_number'] ?? $p->bank_account_number,
            'bank_account_holder' => isset($data['bank_account_holder']) ? strtoupper($data['bank_account_holder']) : $p->bank_account_holder,
        ] + $keys)->save();

        return $p->fresh();
    }
}
