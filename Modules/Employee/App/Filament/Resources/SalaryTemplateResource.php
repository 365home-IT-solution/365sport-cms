<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Forms\SalaryTemplateForm;
use Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Pages;
use Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Tables\SalaryTemplateTable;
use Modules\Employee\Entities\SalaryTemplate;

// Quản lý danh mục Mẫu lương — trước đây chỉ tạo mới được qua nút "+" trong tab "Lương" của form
// Nhân viên, không sửa/xoá lại được sau khi tạo.
class SalaryTemplateResource extends Resource
{
    protected static ?string $model = SalaryTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Mẫu lương';

    protected static ?string $modelLabel = 'Mẫu lương';

    protected static ?string $pluralModelLabel = 'Mẫu lương';

    public static function form(Form $form): Form
    {
        return SalaryTemplateForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return SalaryTemplateTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalaryTemplate::route('/'),
            'create' => Pages\CreateSalaryTemplate::route('/create'),
            'edit'   => Pages\EditSalaryTemplate::route('/{record}/edit'),
        ];
    }
}
