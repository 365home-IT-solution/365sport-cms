<?php

declare(strict_types=1);

namespace Modules\Form\App\Filament\Resources;

use Modules\Form\App\Filament\Resources\FormSubmissionResource\Forms\FormSubmissionForm;
use Modules\Form\App\Filament\Resources\FormSubmissionResource\Tables\FormSubmissionTable;
use Modules\Form\App\Filament\Resources\FormSubmissionResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Form\Entities\FormSubmission;

class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    // Bật lại hiển thị menu — cần thiết để xem lượt gửi form "Đăng ký làm đối tác"
    // (/lam-doi-tac) và các form khác trong hệ thống.
    public static function getNavigationIcon(): string
    {
        return __('form::form-submission.resource.navigation_icon');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 8;
    }
    public static function getNavigationLabel(): string
    {
        return __('form::form-submission.resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('form::form-submission.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('form::form-submission.resource.plural_model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    // Bản ghi do khách gửi form phía website sinh ra, admin chỉ xem/đánh dấu đã xem — không tạo tay.
    public static function canCreate(): bool
    {
        return false;
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
            'index' => Pages\ListFormSubmission::route('/'),
            'create' => Pages\CreateFormSubmission::route('/create'),
            'edit' => Pages\EditFormSubmission::route('/{record}/edit'),
        ];
    }
}
