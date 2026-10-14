<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TournamentResource\Pages;
use App\Filament\Resources\TournamentResource\RelationManagers\MatchesRelationManager;
use App\Filament\Resources\TournamentResource\RelationManagers\RegistrationsRelationManager;
use App\Filament\Support\PartnerTableHelpers;
use App\Models\Partner;
use App\Models\Tournament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Giải đấu do đối tác tổ chức. Đội do khách (trưởng nhóm) đăng ký trên app; ở đây duyệt đội, ghi nhận lệ phí và xếp trận/nhập tỉ số.
class TournamentResource extends Resource
{
    use \App\Filament\Support\GatedByFeature;

    protected static string $featureFlag = 'tournament';

    protected static ?string $model = Tournament::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Giải đấu';

    protected static ?string $modelLabel = 'Giải đấu';

    protected static ?string $pluralModelLabel = 'Giải đấu';

    public static function getEloquentQuery(): Builder
    {
        // Global scope BelongsToPartner đã lọc theo đối tác trong panel; viết lại tường minh để không phụ thuộc vào scope.
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user && ! $user->isSuperAdmin() ? $query->where('partner_id', $user->partner_id) : $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('partner_id')->label('Đối tác')->options(fn () => Partner::query()->orderBy('name')->pluck('name', 'id'))->searchable()
                ->required()->visible(fn () => (bool) auth()->user()?->isSuperAdmin()),
            TextInput::make('title')->label('Tên giải')->required()->maxLength(150)->columnSpanFull(),
            Textarea::make('description')->label('Mô tả / thể lệ')->rows(5)->columnSpanFull(),
            TextInput::make('location')->label('Địa điểm')->maxLength(255),
            FileUpload::make('banner')->label('Ảnh bìa')->image()->disk('public')->directory('tournaments'),
            DatePicker::make('registration_deadline')->label('Hạn đăng ký')->required()->beforeOrEqual('start_date'),
            DatePicker::make('start_date')->label('Ngày bắt đầu')->required()->live(),
            DatePicker::make('end_date')->label('Ngày kết thúc')->required()->afterOrEqual('start_date'),
            TextInput::make('max_teams')->label('Số đội tối đa')->numeric()->integer()->minValue(2)->maxValue(256)->default(16)->required(),
            TextInput::make('entry_fee')->label('Lệ phí mỗi đội (đ)')->numeric()->integer()->minValue(0)->default(0)->required(),
            TextInput::make('prize_info')->label('Giải thưởng')->maxLength(500)->columnSpanFull(),
            Select::make('status')->label('Trạng thái')->options(Tournament::STATUSES)->default('draft')->required()
                ->helperText('Chỉ giải "Đang mở đăng ký" mới nhận đội mới; giải nháp khách không nhìn thấy.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('title')->label('Giải đấu')->searchable()->wrap(),
                TextColumn::make('start_date')->label('Bắt đầu')->date('d/m/Y')->sortable(),
                TextColumn::make('registration_deadline')->label('Hạn đăng ký')->date('d/m/Y'),
                TextColumn::make('registrations_count')->label('Đội đăng ký')->counts('registrations'),
                TextColumn::make('max_teams')->label('Tối đa'),
                TextColumn::make('entry_fee')->label('Lệ phí')->money('VND'),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => Tournament::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['draft' => 'gray', 'open' => 'success', 'ongoing' => 'info', 'finished' => 'primary', 'cancelled' => 'danger'][$state] ?? 'gray'),
                PartnerTableHelpers::column(),
            ])
            ->filters([SelectFilter::make('status')->label('Trạng thái')->options(Tournament::STATUSES), PartnerTableHelpers::filter()])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Tournament $r) => $r->approvedCount() === 0 && ! $r->matches()->exists())
                    ->modalDescription('Giải đã có đội được duyệt hoặc có trận đấu thì không xoá được — hãy chuyển sang "Đã huỷ".'),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [RegistrationsRelationManager::class, MatchesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTournaments::route('/'),
            'create' => Pages\CreateTournament::route('/create'),
            'edit' => Pages\EditTournament::route('/{record}/edit'),
        ];
    }
}
