<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Employee\App\Filament\Resources\AllowanceTypeResource\Forms\AllowanceTypeForm;
use Modules\Employee\App\Filament\Resources\AllowanceTypeResource\Pages;
use Modules\Employee\App\Filament\Resources\AllowanceTypeResource\Tables\AllowanceTypeTable;
use Modules\Employee\Entities\AllowanceType;

// Quản lý danh mục Loại phụ cấp — trước đây chỉ tạo mới được qua nút "+" trong tab "Lương" của
// form Nhân viên, không sửa/xoá lại được sau khi tạo.
class AllowanceTypeResource extends Resource
{
    protected static ?string $model = AllowanceType::class;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Loại phụ cấp';

    protected static ?string $modelLabel = 'Loại phụ cấp';

    protected static ?string $pluralModelLabel = 'Loại phụ cấp';

    // Ẩn khỏi menu theo yêu cầu — vẫn tạo/sửa được qua nút "+" trong tab "Lương" của form Nhân viên.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return AllowanceTypeForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return AllowanceTypeTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAllowanceType::route('/'),
            'create' => Pages\CreateAllowanceType::route('/create'),
            'edit'   => Pages\EditAllowanceType::route('/{record}/edit'),
        ];
    }
}
