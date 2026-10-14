<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerSkillLevelResource\Pages;
use App\Models\Customer;
use App\Models\PlayerSkillLevel;
use App\Services\PlayerSkillService;
use App\Support\SkillLevels;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Trình độ người chơi theo từng môn: khách tự khai trong app, nhân viên sân đánh giá / xác nhận (verified) tại đây.
class PlayerSkillLevelResource extends Resource
{
    protected static ?string $model = PlayerSkillLevel::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Khách hàng';

    protected static ?string $navigationLabel = 'Trình độ người chơi';

    protected static ?string $modelLabel = 'Trình độ';

    protected static ?string $pluralModelLabel = 'Trình độ người chơi';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('customer:id,fullname,phone');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('customer_id')->label('Khách hàng')->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Customer::query()
                    ->where(fn ($q) => $q->where('fullname', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->limit(30)->get()->mapWithKeys(fn ($c) => [$c->id => "{$c->fullname} — {$c->phone}"])->all())
                ->getOptionLabelUsing(fn ($value) => ($c = Customer::find($value)) ? "{$c->fullname} — {$c->phone}" : null)
                ->disabled(fn (?PlayerSkillLevel $record) => $record !== null)
                ->dehydrated(),
            TextInput::make('sport_type')->label('Môn (mã)')->default(SkillLevels::GENERAL)->required()->maxLength(100)
                ->helperText('"general" = trình độ chung; hoặc mã môn trùng với "Môn thể thao" của loại sân (vd pickleball, football).')
                ->disabled(fn (?PlayerSkillLevel $record) => $record !== null)
                ->dehydrated(),
            Select::make('level')->label('Trình độ')->required()
                ->options(collect(SkillLevels::MAP)->map(fn ($m) => $m['label'])->all()),
            Textarea::make('note')->label('Ghi chú đánh giá')->rows(2)->maxLength(500),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('customer.fullname')->label('Khách')->searchable()->description(fn (PlayerSkillLevel $r) => $r->customer?->phone),
                TextColumn::make('sport_type')->label('Môn')->badge()->formatStateUsing(fn ($state) => $state === SkillLevels::GENERAL ? 'Chung' : $state)->searchable(),
                TextColumn::make('level')->label('Trình độ')->badge()
                    ->formatStateUsing(fn ($state) => SkillLevels::label($state))
                    ->color(fn ($state) => match ($state) {
                        'beginner' => 'gray', 'intermediate' => 'info', 'advanced' => 'warning', 'pro' => 'success', default => 'gray',
                    }),
                IconColumn::make('source')->label('Đã xác nhận')->boolean()
                    ->getStateUsing(fn (PlayerSkillLevel $r) => $r->source === PlayerSkillLevel::SOURCE_STAFF),
                TextColumn::make('assessment_requested_at')->label('Xin đánh giá lại')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('note')->label('Ghi chú')->limit(40)->placeholder('—'),
                TextColumn::make('updated_at')->label('Cập nhật')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('level')->label('Trình độ')->options(collect(SkillLevels::MAP)->map(fn ($m) => $m['label'])->all()),
                SelectFilter::make('source')->label('Nguồn')->options(['self' => 'Khách tự khai', 'staff' => 'Nhân viên xác nhận']),
                TernaryFilter::make('requested')->label('Xin đánh giá lại')->queries(
                    true: fn (Builder $q) => $q->whereNotNull('assessment_requested_at'),
                    false: fn (Builder $q) => $q->whereNull('assessment_requested_at'),
                ),
            ])
            ->actions([
                Action::make('assess')->label('Đánh giá')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn () => (bool) auth()->user()?->can('update_player::skill::level'))
                    ->form([
                        Select::make('level')->label('Trình độ xác nhận')->required()
                            ->options(collect(SkillLevels::MAP)->map(fn ($m) => $m['label'])->all()),
                        Textarea::make('note')->label('Ghi chú')->rows(2)->maxLength(500),
                    ])
                    ->fillForm(fn (PlayerSkillLevel $record) => ['level' => $record->level, 'note' => $record->note])
                    ->action(function (PlayerSkillLevel $record, array $data): void {
                        app(PlayerSkillService::class)->set(
                            Customer::findOrFail($record->customer_id), $record->sport_type, $data['level'],
                            PlayerSkillLevel::SOURCE_STAFF, (string) auth()->id(), $data['note'] ?? null,
                        );
                        Notification::make()->success()->title('Đã xác nhận trình độ')->send();
                    }),
                DeleteAction::make()->using(function (PlayerSkillLevel $record): void {
                    app(PlayerSkillService::class)->set(
                        Customer::findOrFail($record->customer_id), $record->sport_type, null,
                        PlayerSkillLevel::SOURCE_STAFF, (string) auth()->id(), 'Removed by staff',
                    );
                }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlayerSkillLevels::route('/'),
            'create' => Pages\CreatePlayerSkillLevel::route('/create'),
        ];
    }
}
