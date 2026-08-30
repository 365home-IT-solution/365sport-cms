<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Employee\App\Filament\Resources\PositionResource\Forms\PositionForm;
use Modules\Employee\App\Filament\Resources\PositionResource\Pages;
use Modules\Employee\App\Filament\Resources\PositionResource\Tables\PositionTable;
use Modules\Employee\Entities\Position;

// Quản lý danh mục Chức danh — cùng lý do như DepartmentResource: trước đây chỉ tạo mới được qua
// nút "+" trong form Nhân viên, không sửa/xoá lại được.
class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Chức danh';

    protected static ?string $modelLabel = 'Chức danh';

    protected static ?string $pluralModelLabel = 'Chức danh';

    public static function form(Form $form): Form
    {
        return PositionForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return PositionTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosition::route('/'),
            'create' => Pages\CreatePosition::route('/create'),
            'edit'   => Pages\EditPosition::route('/{record}/edit'),
        ];
    }
}
