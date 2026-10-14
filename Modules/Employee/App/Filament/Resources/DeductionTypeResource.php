<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Employee\App\Filament\Resources\DeductionTypeResource\Forms\DeductionTypeForm;
use Modules\Employee\App\Filament\Resources\DeductionTypeResource\Pages;
use Modules\Employee\App\Filament\Resources\DeductionTypeResource\Tables\DeductionTypeTable;
use Modules\Employee\Entities\DeductionType;

// Quản lý danh mục Loại giảm trừ — trước đây chỉ tạo mới được qua nút "+" trong tab "Lương" của
// form Nhân viên, không sửa/xoá lại được sau khi tạo.
class DeductionTypeResource extends Resource
{
    use \App\Filament\Support\GatedByFeature;

    protected static string $featureFlag = 'payroll';

    protected static ?string $model = DeductionType::class;

    protected static ?string $navigationIcon = 'heroicon-o-minus-circle';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Loại giảm trừ';

    protected static ?string $modelLabel = 'Loại giảm trừ';

    protected static ?string $pluralModelLabel = 'Loại giảm trừ';

    // Ẩn khỏi menu theo yêu cầu — vẫn tạo/sửa được qua nút "+" trong tab "Lương" của form Nhân viên.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return DeductionTypeForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return DeductionTypeTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDeductionType::route('/'),
            'create' => Pages\CreateDeductionType::route('/create'),
            'edit'   => Pages\EditDeductionType::route('/{record}/edit'),
        ];
    }
}
