<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Models\GroupMember;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class GroupMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';
    protected static ?string $recordTitleAttribute = 'user.name';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                // Member Name
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Member')
                    ->sortable()
                    ->searchable(),

                // Role
                Tables\Columns\TextColumn::make('role')
                    ->sortable(),

                // Contribution status (Partial / Paid)
                Tables\Columns\TextColumn::make('contribution_status')
                    ->label('Contribution')
                    ->getStateUsing(function ($record) {
                        $group = $record->group;
                        $user = $record->user;

                        // Sum contributions for current period
                        $totalPaid = $user->contributions()
                            ->where('group_id', $group->id)
                            ->whereBetween('paid_at', [$group->currentPeriodStart(), $group->currentPeriodEnd()])
                            ->sum('amount');

                        $amountOwing = $group->contribution_amount - $totalPaid;

                        return $amountOwing <= 0
                            ? "Paid: " . number_format($group->contribution_amount, 2)
                            : "Amount Owing: " . number_format($amountOwing, 2);
                    })
                    ->color(function ($record) {
                        $group = $record->group;
                        $user = $record->user;

                        $totalPaid = $user->contributions()
                            ->where('group_id', $group->id)
                            ->whereBetween('paid_at', [$group->currentPeriodStart(), $group->currentPeriodEnd()])
                            ->sum('amount');

                        return $totalPaid >= $group->contribution_amount ? 'success' : 'danger';
                    }),

                // Loan Owing
                Tables\Columns\TextColumn::make('loan_status')
                    ->label('Loan Owing')
                    ->getStateUsing(function ($record) {
                        $user = $record->user;
                        $group = $record->group;

                        $loan = $user->loans()
                            ->where('group_id', $group->id)
                            ->whereIn('status', ['pending','approved'])
                            ->latest()
                            ->first();

                        if (!$loan) return 'No Loan';

                        $paid = $loan->payments()->sum('amount');
                        $remaining = $loan->total_amount - $paid;

                        return 'Owing: ' . number_format($remaining, 2);
                    })
                    ->color(function ($record) {
                        $user = $record->user;
                        $group = $record->group;

                        $loan = $user->loans()
                            ->where('group_id', $group->id)
                            ->whereIn('status', ['pending','approved'])
                            ->latest()
                            ->first();

                        if (!$loan) return 'success';

                        $paid = $loan->payments()->sum('amount');
                        $remaining = $loan->total_amount - $paid;

                        return $remaining > 0 ? 'danger' : 'success';
                    }),

                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Member')
                    ->options(\App\Models\User::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Admin',
                        'member' => 'Member',
                    ])
                    ->required()
                    ->default('member'),
            ]);
    }
}