<?php

declare(strict_types=1);

namespace Modules\Form\App\Filament\Resources;

use Modules\Form\App\Filament\Resources\FormResource\Forms\FormForm;
use Modules\Form\App\Filament\Resources\FormResource\Tables\FormTable;
use Modules\Form\App\Filament\Resources\FormResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Form\Entities\Form;

class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    // Bật lại hiển thị menu — cần thiết để quản lý form "Đăng ký làm đối tác" (/lam-doi-tac) và
    // cấu hình email thông báo (EmailSetting) khi có người gửi.
    public static function getNavigationIcon(): string
    {
        return __('form::form.resource.navigation_icon');
    }
    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }
    public static function getNavigationLabel(): string
    {
        return __('form::form.resource.navigation_label');
    }
    
    public static function getModelLabel(): string
    {
        return __('form::form.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('form::form.resource.plural_model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return FormForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return FormTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForm::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
    }
}
