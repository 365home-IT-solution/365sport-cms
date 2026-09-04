<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Employee\App\Filament\Resources\DepartmentResource\Forms\DepartmentForm;
use Modules\Employee\App\Filament\Resources\DepartmentResource\Pages;
use Modules\Employee\App\Filament\Resources\DepartmentResource\Tables\DepartmentTable;
use Modules\Employee\Entities\Department;

// Quản lý danh mục Phòng ban — trước đây chỉ tạo được MỚI qua nút "+" ngay trong form Nhân viên
// (Modules\User\App\Filament\Resources\UserResource\Forms\UserForm::createOptionUsing()), không
// có chỗ nào sửa/xoá lại sau khi tạo (vd gõ nhầm tên). Trang này bổ sung đúng phần còn thiếu đó,
// KHÔNG đụng tới luồng tạo/sửa Nhân viên (vẫn nằm nguyên trong UserResource).
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Phòng ban';

    protected static ?string $modelLabel = 'Phòng ban';

    protected static ?string $pluralModelLabel = 'Phòng ban';

    // Ẩn khỏi menu theo yêu cầu — vẫn tạo/sửa được qua nút "+" ngay trong form Nhân viên
    // (UserForm::createOptionUsing()), chỉ không còn trang danh sách riêng ở menu.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return DepartmentForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return DepartmentTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDepartment::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit'   => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
