<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContributionResource\Pages;
use App\Filament\Resources\ContributionResource\RelationManagers;
use App\Models\Contribution;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form->schema([
        // 1️⃣ Select Group
        Select::make('group_id')
            ->label('Group')
            ->options(\App\Models\Group::pluck('name', 'id')) // id => name
            ->reactive() // triggers updates on dependent fields
            ->required(),

        // 2️⃣ Select User based on group
        Select::make('user_id')
            ->label('Member')
            ->options(function (callable $get) {
                $groupId = $get('group_id');
                if (!$groupId) return [];

                // Get users in the selected group
                return \App\Models\GroupMember::where('group_id', $groupId)
                    ->with('user')
                    ->get()
                    ->pluck('user.name', 'user.id');
            })
            ->searchable() // enables search by name
            ->required(),

        // 3️⃣ Contribution amount
        TextInput::make('amount')
            ->numeric()
            ->default(fn(callable $get) => optional(\App\Models\Group::find($get('group_id')))->contribution_amount)
            ->required()
            ->minValue(0)
            ->maxValue(fn(callable $get) => optional(\App\Models\Group::find($get('group_id')))->contribution_amount),

        // 4️⃣ Paid at
        TextInput::make('paid_at')
            ->type('datetime-local')
            ->required(),
    ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('group_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListContributions::route('/'),
            'create' => Pages\CreateContribution::route('/create'),
            'edit' => Pages\EditContribution::route('/{record}/edit'),
        ];
    }
}
