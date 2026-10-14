<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Employee\App\Filament\Resources\SalaryTypeResource\Forms\SalaryTypeForm;
use Modules\Employee\App\Filament\Resources\SalaryTypeResource\Pages;
use Modules\Employee\App\Filament\Resources\SalaryTypeResource\Tables\SalaryTypeTable;
use Modules\Employee\Entities\SalaryType;

// Quản lý danh mục Loại lương — trước đây chỉ tạo mới được qua nút "+" trong tab "Lương" của form
// Nhân viên (UserForm::createSalaryTab()), không sửa/xoá lại được sau khi tạo.
class SalaryTypeResource extends Resource
{
    use \App\Filament\Support\GatedByFeature;

    protected static string $featureFlag = 'payroll';

    protected static ?string $model = SalaryType::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Loại lương';

    protected static ?string $modelLabel = 'Loại lương';

    protected static ?string $pluralModelLabel = 'Loại lương';

    // Ẩn khỏi menu theo yêu cầu — vẫn tạo/sửa được qua nút "+" trong tab "Lương" của form Nhân viên.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return SalaryTypeForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return SalaryTypeTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalaryType::route('/'),
            'create' => Pages\CreateSalaryType::route('/create'),
            'edit'   => Pages\EditSalaryType::route('/{record}/edit'),
        ];
    }
}
