<?php

namespace Modules\BladeThemeV1\Livewire;

use Livewire\Component;
use Modules\ThemeSetting\App\Models\ThemeSection;
use Modules\BladeThemeV1\Enums\ContactLinkSection;
use Modules\BladeThemeV1\Traits\HandleSectionCfgTrait;

class ContactLink extends Component
{
    use HandleSectionCfgTrait;

    public array $contact_button = [];

    public ?ThemeSection $section = null;

    public function mount(): void
    {
        // ThemeSection 'contact_buttons' có thể chưa tồn tại (DB mới/chưa seed đủ) — bỏ qua, giữ
        // $contact_button rỗng thay vì crash TypeError khi gán null vào property không nullable
        // (bug thật đã gặp: Cannot assign null to property ...ContactLink::$section).
        $this->section = $this->getContactLinkConfigs();

        if ($this->section) {
            $this->contact_button = $this->getChildSectionConfigs(ContactLinkSection::CONTACT_LINK_DETAIL->value);
        }
    }

    private function getContactLinkConfigs()
    {
        return ThemeSection::where('name', 'contact_buttons')
            ->with(['children'])
            ->first();
    }

    public function render()
    {
        return view('bladethemev1::livewire.contact-link');
    }
}
