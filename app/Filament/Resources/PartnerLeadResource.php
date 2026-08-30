<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerLeadResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Form\App\Filament\Resources\FormSubmissionResource\Forms\FormSubmissionForm;
use Modules\Form\App\Filament\Resources\FormSubmissionResource\Tables\FormSubmissionTable;
use Modules\Form\Entities\FormSubmission;

// Menu RIÊNG chỉ hiển thị các lượt gửi form "Đăng ký làm đối tác" (/lam-doi-tac) — tách khỏi menu
// "Biểu mẫu" chung (gộp lẫn với form Liên hệ và các form khác trong tương lai, khó theo dõi riêng
// lead đối tác). Dùng LẠI nguyên vẹn Form/Table component của Modules\Form (không viết lại logic
// xem/đánh dấu đã xem/xoá) — chỉ khác đúng 1 chỗ: getEloquentQuery() lọc riêng theo tên form.
class PartnerLeadResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Yêu cầu đăng ký';

    protected static ?string $modelLabel = 'Yêu cầu đăng ký đối tác';

    protected static ?string $pluralModelLabel = 'Yêu cầu đăng ký đối tác';

    protected static ?string $slug = 'partner-leads';

    protected static ?int $navigationSort = 3;

    private const FORM_NAME = 'Đăng ký làm đối tác';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('form', fn (Builder $q) => $q->where('name', self::FORM_NAME));
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('is_viewed', false)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return (int) static::getNavigationBadge() > 0 ? 'danger' : null;
    }

    private static function isSuperAdmin(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    public static function form(Form $form): Form
    {
        return FormSubmissionForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return FormSubmissionTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerLeads::route('/'),
            'edit'  => Pages\EditPartnerLead::route('/{record}/edit'),
        ];
    }
}
