<?php

namespace Modules\BladeThemeV1\Livewire\BookingFund;

use App\Models\BookingFund;
use App\Models\BookingFundContribution;
use App\Models\BookingFundMember;
use App\Models\BookingFundMessage;
use App\Models\Customer;
use App\Services\BookingFundChatRealtimeService;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;
use Livewire\WithFileUploads;

// Trang chi tiết 1 quỹ nhóm (theo invite_code) — 1 component đa trạng thái theo VAI TRÒ của người
// đang xem, xem plan "Quỹ nhóm góp tiền đặt sân": khách lạ (chưa xin tham gia) → thành viên đang
// chờ duyệt → thành viên đã duyệt (thấy QR + góp tiền) → trưởng nhóm (thêm quyền duyệt thành
// viên/khoản góp + đóng quỹ). Auth qua token localStorage, cùng pattern prefillFromAuth() của
// ProductDetail.php/Book.php — bắt buộc đăng nhập mới thao tác được (chỉ xem tên/mục tiêu quỹ nếu
// chưa đăng nhập).
class FundDetail extends Component
{
    use WithFileUploads;

    public string $inviteCode;

    // true khi được nhúng ngay trong khung "Nhóm của tôi" của account-page.blade.php thay vì trang
    // riêng /quy-nhom/{code} — chỉ ảnh hưởng layout (bỏ min-h-screen full-bleed), không đổi logic.
    public bool $embedded = false;

    public bool    $isAuthUser = false;
    public ?string $authUserId = null;
    public string  $authUserName = '';

    // Form góp quỹ (thành viên đã duyệt)
    public string $contributeAmount = '';
    public $receiptPhoto = null;

    // Chat nhóm — chỉ trưởng nhóm + thành viên đã duyệt (xem canChat()).
    public string $messageBody = '';

    public function mount(string $code): void
    {
        $this->inviteCode = strtoupper($code);
    }

    public function prefillFromAuth(string $token): void
    {
        if (empty($token)) {
            $this->isAuthUser = false;
            $this->authUserId = null;
            return;
        }

        try {
            $pat = PersonalAccessToken::findToken($token);
            if (! $pat || $pat->tokenable_type !== Customer::class) {
                return;
            }

            /** @var Customer $customer */
            $customer = $pat->tokenable;
            if (! $customer || $customer->status !== Customer::STATUS_ACTIVE) {
                return;
            }

            $this->isAuthUser   = true;
            $this->authUserId   = $customer->id;
            $this->authUserName = $customer->fullname ?? 'Khách hàng';
        } catch (\Throwable) {
            // Silent fail — hiện lại màn "chưa đăng nhập"
        }
    }

    private function fund(): ?BookingFund
    {
        return BookingFund::where('invite_code', $this->inviteCode)->first();
    }

    private function myMembership(BookingFund $fund): ?BookingFundMember
    {
        if (! $this->authUserId) {
            return null;
        }

        return $fund->members()->where('customer_id', $this->authUserId)->first();
    }

    // Xin tham gia — khách lạ đã đăng nhập, chưa từng xin tham gia quỹ này.
    public function requestJoin(): void
    {
        $fund = $this->fund();
        if (! $fund || ! $this->isAuthUser || ! $this->authUserId) {
            return;
        }

        if ($fund->isLeader($this->authUserId)) {
            return;
        }

        BookingFundMember::firstOrCreate(
            ['booking_fund_id' => $fund->id, 'customer_id' => $this->authUserId],
            ['status' => 'pending', 'requested_at' => now()]
        );

        $this->dispatch('notify', ['message' => 'Đã gửi yêu cầu tham gia — chờ trưởng nhóm duyệt.', 'type' => 'success']);
    }

    // ── Trưởng nhóm: duyệt/từ chối thành viên ─────────────────────────────────
    public function decideMember(int $memberId, string $decision): void
    {
        $fund = $this->fund();
        if (! $fund || ! $fund->isLeader($this->authUserId) || ! in_array($decision, ['approved', 'rejected'], true)) {
            return;
        }

        $member = $fund->members()->whereKey($memberId)->first();
        if (! $member) {
            return;
        }

        $member->update(['status' => $decision, 'decided_at' => now()]);
    }

    // ── Thành viên đã duyệt: gửi 1 lượt góp quỹ kèm ảnh biên lai ──────────────
    public function submitContribution(): void
    {
        $fund = $this->fund();
        if (! $fund || $fund->status !== 'collecting') {
            return;
        }

        $membership = $this->myMembership($fund);
        if (! $membership || $membership->status !== 'approved') {
            return;
        }

        $this->validate([
            'contributeAmount' => 'required|numeric|min:1000',
            'receiptPhoto'     => 'required|image|max:8192',
        ], [
            'contributeAmount.required' => 'Vui lòng nhập số tiền đã chuyển.',
            'receiptPhoto.required'     => 'Vui lòng tải lên ảnh biên lai chuyển khoản.',
        ]);

        $path = $this->receiptPhoto->store('booking-fund-receipts', 'public');

        BookingFundContribution::create([
            'booking_fund_id' => $fund->id,
            'customer_id'     => $this->authUserId,
            'amount'          => (int) $this->contributeAmount,
            'receipt_photo'   => $path,
            'status'          => 'pending',
        ]);

        $this->reset(['contributeAmount', 'receiptPhoto']);
        $this->dispatch('notify', ['message' => 'Đã gửi — chờ trưởng nhóm xác nhận.', 'type' => 'success']);
    }

    // ── Trưởng nhóm: xác nhận/từ chối 1 khoản góp ─────────────────────────────
    public function decideContribution(int $contributionId, string $decision): void
    {
        $fund = $this->fund();
        if (! $fund || ! $fund->isLeader($this->authUserId) || ! in_array($decision, ['confirmed', 'rejected'], true)) {
            return;
        }

        $contribution = $fund->contributions()->whereKey($contributionId)->first();
        if (! $contribution || $contribution->status !== 'pending') {
            return;
        }

        $contribution->update([
            'status'       => $decision,
            'confirmed_at' => $decision === 'confirmed' ? now() : null,
        ]);
    }

    public function closeFund(): void
    {
        $fund = $this->fund();
        if (! $fund || ! $fund->isLeader($this->authUserId) || $fund->status !== 'collecting') {
            return;
        }

        $fund->update(['status' => 'closed', 'closed_at' => now()]);
        $this->dispatch('notify', ['message' => 'Đã đóng quỹ.', 'type' => 'success']);
    }

    // Chat nhóm chỉ mở cho trưởng nhóm + thành viên đã được duyệt (khách lạ/đang chờ duyệt không
    // xem/gửi được) — cùng điều kiện với việc xem danh sách contributions.
    private function canChat(BookingFund $fund, ?BookingFundMember $membership, bool $isLeader): bool
    {
        return $isLeader || ($membership && $membership->status === 'approved');
    }

    public function sendMessage(): void
    {
        $fund = $this->fund();
        if (! $fund || ! $this->isAuthUser || ! $this->authUserId) {
            return;
        }

        $isLeader   = $fund->isLeader($this->authUserId);
        $membership = $this->myMembership($fund);
        if (! $this->canChat($fund, $membership, $isLeader)) {
            return;
        }

        $body = trim($this->messageBody);
        if ($body === '' || mb_strlen($body) > 1000) {
            return;
        }

        $message = BookingFundMessage::create([
            'booking_fund_id' => $fund->id,
            'customer_id'     => $this->authUserId,
            'body'            => $body,
        ]);

        $this->messageBody = '';

        app(BookingFundChatRealtimeService::class)->broadcastMessage($fund->id, [
            'id'           => $message->id,
            'customer_id'  => $this->authUserId,
            'sender_name'  => $this->authUserName,
            'body'         => $message->body,
            'created_at'   => $message->created_at->format('H:i'),
        ]);
    }

    public function render()
    {
        $fund = $this->fund();

        $isLeader   = $fund && $this->isAuthUser && $fund->isLeader($this->authUserId);
        $membership = $fund ? $this->myMembership($fund) : null;
        $canChat    = $fund ? $this->canChat($fund, $membership, $isLeader) : false;

        return view('bladethemev1::livewire.booking-fund.fund-detail', [
            'fund'       => $fund,
            'isLeader'   => $isLeader,
            'membership' => $membership,
            'canChat'    => $canChat,
            'messages'   => $canChat
                ? $fund->messages()->with('customer')->orderBy('created_at')->get()
                : collect(),
            'members'    => $isLeader ? $fund->members()->with('customer')->orderByDesc('requested_at')->get() : collect(),
            'contributions' => ($isLeader || ($membership && $membership->status === 'approved'))
                ? $fund->contributions()
                    ->with('customer')
                    ->when(! $isLeader, fn ($q) => $q->where('customer_id', $this->authUserId))
                    ->orderByDesc('created_at')
                    ->get()
                : collect(),
        ]);
    }
}
