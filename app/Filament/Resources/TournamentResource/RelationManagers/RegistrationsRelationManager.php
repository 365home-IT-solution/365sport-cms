<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\RelationManagers;

use App\Models\TournamentTeam;
use App\Services\TournamentService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Đội đăng ký';

    public function table(Table $table): Table
    {
        $label = ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'];

        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['team:id,name,code,leader_customer_id', 'team.leader:id,fullname,phone']))
            ->columns([
                TextColumn::make('team.name')->label('Đội'),
                TextColumn::make('team.code')->label('Mã đội'),
                TextColumn::make('team.leader.fullname')->label('Trưởng nhóm'),
                TextColumn::make('team.leader.phone')->label('SĐT')->copyable(),
                TextColumn::make('status')->label('Trạng thái')->badge()->formatStateUsing(fn ($state) => $label[$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
                IconColumn::make('fee_paid')->label('Đã nộp lệ phí')->boolean(),
                TextColumn::make('created_at')->label('Đăng ký lúc')->dateTime('d/m/Y H:i'),
            ])
            ->actions([
                Action::make('approve')->label('Duyệt')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (TournamentTeam $r) => $r->status !== 'approved' && $this->canManageTournament())
                    ->action(function (TournamentTeam $record): void {
                        $result = app(TournamentService::class)->decide($record, 'approved');
                        $result['ok'] ? Notification::make()->success()->title('Đã duyệt đội')->send() : Notification::make()->danger()->title($result['message'])->send();
                    }),
                Action::make('reject')->label('Từ chối')->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                    ->visible(fn (TournamentTeam $r) => $r->status === 'pending' && $this->canManageTournament())
                    ->action(fn (TournamentTeam $record) => app(TournamentService::class)->decide($record, 'rejected')),
                Action::make('toggle_fee')->label(fn (TournamentTeam $r) => $r->fee_paid ? 'Bỏ đã nộp' : 'Đã nộp lệ phí')->icon('heroicon-o-banknotes')
                    ->visible(fn () => $this->canManageTournament())
                    ->action(fn (TournamentTeam $record) => $record->update(['fee_paid' => ! $record->fee_paid])),
            ])
            ->headerActions([]);
    }

    private function canManageTournament(): bool
    {
        return (bool) auth()->user()?->can('update_tournament');
    }
}
