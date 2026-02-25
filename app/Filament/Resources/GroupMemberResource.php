<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupMemberResource\Pages;
use App\Filament\Resources\GroupMemberResource\RelationManagers;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GroupMemberResource extends Resource
{
    protected static ?string $model = GroupMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->options(User::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('group_id')
                    ->label('Group')
                    ->options(Group::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'member' => 'Member',
                    ])
                    ->required()
                    ->default('member'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Group')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('role'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contribution_status')
    ->label('Contribution')
    ->getStateUsing(function ($record) {
        $group = $record->group;
        $user = $record->user;

        // Calculate the last period
        $lastPeriodEnd = $group->periodEnd(now()->subDay());
        $lastPeriodStart = $group->periodStart(now()->subDay());

        $contribution = $user->contributions()
            ->where('group_id', $group->id)
            ->whereBetween('paid_at', [$lastPeriodStart, $lastPeriodEnd])
            ->latest()
            ->first();

        if ($contribution) {
            return "Paid: " . number_format($contribution->amount, 2);
        }

        return "Amount Owing: " . number_format($group->contribution_amount, 2);
    })
    ->color(function ($record) {
        $group = $record->group;
        $user = $record->user;

        $lastPeriodEnd = $group->periodEnd(now()->subDay());
        $lastPeriodStart = $group->periodStart(now()->subDay());

        $contribution = $user->contributions()
            ->where('group_id', $group->id)
            ->whereBetween('paid_at', [$lastPeriodStart, $lastPeriodEnd])
            ->latest()
            ->first();

        return $contribution ? 'success' : 'danger'; // Green if paid, Red if owing
    })
                
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroupMembers::route('/'),
            'create' => Pages\CreateGroupMember::route('/create'),
            'edit' => Pages\EditGroupMember::route('/{record}/edit'),
        ];
    }
}
