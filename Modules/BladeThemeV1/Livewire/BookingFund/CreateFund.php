<?php

namespace Modules\BladeThemeV1\Livewire\BookingFund;

use App\Models\BookingFund;
use App\Models\Customer;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;

// Tạo quỹ nhóm góp tiền đặt sân — xem plan "Quỹ nhóm góp tiền đặt sân". Bắt buộc đăng nhập (auth
// qua token localStorage, CÙNG PATTERN prefillFromAuth() của ProductDetail.php/Book.php) — quỹ
// phải gắn với 1 Customer thật để tra cứu "quỹ của tôi" sau này.
class CreateFund extends Component
{
    public bool   $isAuthUser = false;
    public ?string $authUserId = null;

    // true khi được nhúng NGAY TRONG trang tài khoản (account-page.blade.php, "Nhóm của tôi") thay
    // vì trang riêng /quy-nhom/tao-moi — tạo xong KHÔNG redirect (sẽ điều hướng cả trình duyệt ra
    // khỏi khung nhúng), thay vào đó bắn event để AccountPage tự chuyển sang xem quỹ vừa tạo.
    public bool $embedded = false;

    public string $title        = '';
    public string $note         = '';
    public string $targetAmount = '';
    public string $bankBin      = '';
    public string $bankAccountNumber = '';
    public string $bankAccountHolder = '';

    // Chọn chi nhánh/sân/khung giờ dự định đặt — CHỈ ĐỂ MINH BẠCH cho thành viên biết đang góp
    // cho sân/giờ nào, KHÔNG giữ chỗ thật (xem BookingFund::slotLabel()). Toàn bộ optional.
    public string $categoryId     = '';
    public string $productId      = '';
    public string $slotDate       = '';
    public string $slotStartTime  = '';
    public string $slotEndTime    = '';

    public function updatedCategoryId(): void
    {
        $this->productId = '';
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

            $this->isAuthUser = true;
            $this->authUserId = $customer->id;
        } catch (\Throwable) {
            // Silent fail — hiện lại màn "chưa đăng nhập"
        }
    }

    public function create(): void
    {
        if (! $this->isAuthUser || ! $this->authUserId) {
            $this->dispatch('notify', ['message' => 'Vui lòng đăng nhập trước.', 'type' => 'error']);
            return;
        }

        $this->validate([
            'title'             => 'required|min:3|max:150',
            'note'              => 'nullable|max:1000',
            'targetAmount'      => 'required|numeric|min:1000',
            'bankBin'           => 'required',
            'bankAccountNumber' => 'required|max:50',
            'bankAccountHolder' => 'required|max:150',
            'categoryId'        => 'nullable|exists:categories,id',
            'productId'         => 'nullable|exists:products,id',
            'slotDate'          => 'nullable|date',
            'slotStartTime'     => 'nullable',
            'slotEndTime'       => 'nullable',
        ], [
            'title.required'             => 'Vui lòng nhập tên quỹ.',
            'targetAmount.required'      => 'Vui lòng nhập số tiền mục tiêu.',
            'targetAmount.min'           => 'Số tiền mục tiêu quá nhỏ.',
            'bankBin.required'           => 'Vui lòng chọn ngân hàng.',
            'bankAccountNumber.required' => 'Vui lòng nhập số tài khoản.',
            'bankAccountHolder.required' => 'Vui lòng nhập tên chủ tài khoản.',
        ]);

        // Sân phải thuộc đúng chi nhánh đã chọn — chặn khách sửa tay giá trị Select qua devtools.
        if ($this->productId && $this->categoryId) {
            $belongs = Product::whereKey($this->productId)
                ->whereHas('categories', fn ($q) => $q->where('categories.id', $this->categoryId))
                ->exists();
            if (! $belongs) {
                $this->productId = '';
            }
        }

        $fund = BookingFund::create([
            'leader_customer_id' => $this->authUserId,
            'title'              => $this->title,
            'note'               => $this->note ?: null,
            'target_amount'      => (int) $this->targetAmount,
            'category_id'        => $this->categoryId ?: null,
            'product_id'         => $this->productId ?: null,
            'slot_date'          => $this->slotDate ?: null,
            'slot_start_time'    => $this->slotStartTime ?: null,
            'slot_end_time'      => $this->slotEndTime ?: null,
            'bank_bin'           => $this->bankBin,
            'bank_account_number' => $this->bankAccountNumber,
            'bank_account_holder' => strtoupper($this->bankAccountHolder),
        ]);

        if ($this->embedded) {
            $this->dispatch('fund-created', code: $fund->invite_code);
            return;
        }

        $this->redirect(route('booking-fund.detail', ['code' => $fund->invite_code]), navigate: false);
    }

    public function render()
    {
        return view('bladethemev1::livewire.booking-fund.create-fund', [
            'banks'     => config('vietnam_banks'),
            'branches'  => Category::where('category_type', 'product')->orderBy('name')->get(['id', 'name']),
            'products'  => $this->categoryId
                ? Product::where('is_activated', true)
                    ->whereHas('categories', fn ($q) => $q->where('categories.id', $this->categoryId))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
        ]);
    }
}
