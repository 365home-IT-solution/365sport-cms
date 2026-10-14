<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\RelationManagers;

use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Services\TournamentService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Xếp trận và nhập tỉ số. Chỉ chọn được các đội đã duyệt của giải; lưu qua TournamentService để luật kiểm tra giống API.
class MatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'matches';

    protected static ?string $title = 'Trận đấu';

    public function form(Form $form): Form
    {
        return $form->schema($this->matchSchema())->columns(2);
    }

    private function matchSchema(): array
    {
        $teams = fn () => Team::query()->whereIn('id', TournamentTeam::query()->where('tournament_id', $this->getOwnerRecord()->id)->where('status', 'approved')->select('team_id'))->pluck('name', 'id');

        return [
            Select::make('team_a_id')->label('Đội A')->options($teams)->required()->different('team_b_id'),
            Select::make('team_b_id')->label('Đội B')->options($teams)->required()->different('team_a_id'),
            TextInput::make('round')->label('Vòng / bảng')->maxLength(50),
            DateTimePicker::make('scheduled_at')->label('Giờ thi đấu'),
            TextInput::make('venue')->label('Sân')->maxLength(255),
            TextInput::make('score_a')->label('Tỉ số đội A')->numeric()->integer()->minValue(0)->maxValue(999),
            TextInput::make('score_b')->label('Tỉ số đội B')->numeric()->integer()->minValue(0)->maxValue(999),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('scheduled_at')
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['teamA:id,name', 'teamB:id,name']))
            ->columns([
                TextColumn::make('round')->label('Vòng')->placeholder('—'),
                TextColumn::make('scheduled_at')->label('Giờ')->dateTime('d/m/Y H:i')->placeholder('Chưa xếp'),
                TextColumn::make('teamA.name')->label('Đội A'),
                TextColumn::make('score')->label('Tỉ số')->state(fn (TournamentMatch $r) => $r->status === 'finished' ? "{$r->score_a} - {$r->score_b}" : '—')->alignCenter(),
                TextColumn::make('teamB.name')->label('Đội B'),
                TextColumn::make('venue')->label('Sân')->placeholder('—'),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => $state === 'finished' ? 'Đã đá' : 'Chưa đá')->color(fn ($state) => $state === 'finished' ? 'success' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make()->label('Thêm trận')->visible(fn () => $this->canManageTournament())
                    ->using(function (array $data) {
                        $result = app(TournamentService::class)->saveMatch($this->getOwnerRecord(), $data);
                        if (! $result['ok']) {
                            Notification::make()->danger()->title($result['message'])->send();
                            $this->halt();
                        }

                        return $result['match'];
                    }),
            ])
            ->actions([
                Action::make('edit_match')->label('Sửa / nhập tỉ số')->icon('heroicon-o-pencil-square')->visible(fn () => $this->canManageTournament())
                    ->form(fn () => $this->matchSchema())
                    ->fillForm(fn (TournamentMatch $record) => $record->only(['team_a_id', 'team_b_id', 'round', 'scheduled_at', 'venue', 'score_a', 'score_b']))
                    ->action(function (TournamentMatch $record, array $data): void {
                        $result = app(TournamentService::class)->saveMatch($this->getOwnerRecord(), $data, $record);
                        $result['ok'] ? Notification::make()->success()->title('Đã lưu trận đấu')->send() : Notification::make()->danger()->title($result['message'])->send();
                    }),
                DeleteAction::make()->visible(fn () => $this->canManageTournament()),
            ]);
    }

    private function canManageTournament(): bool
    {
        return (bool) auth()->user()?->can('update_tournament');
    }
}
