<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupResource\Pages;
use App\Filament\Resources\GroupResource\RelationManagers;
use App\Models\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getRelations(): array
    {
        return [
            RelationManagers\GroupMembersRelationManager::class,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Group Name')
                    ->required(),

                Select::make('type')
                    ->label('Group Type')
                    ->options([
                        'contribution' => 'Contribution',
                        'rounds' => 'Rounds',
                        'shared' => 'Shared Fund',
                    ])
                    ->live()
                    ->required(),

                TextInput::make('interest_rate')
                    ->label('Interest Rate')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->visible(fn (callable $get) => $get('type') === 'shared')
                    ->required(fn (callable $get) => $get('type') === 'shared'),

                TextInput::make('target_amount')
                    ->label('Target Amount')
                    ->numeric()
                    ->required(),

                TextInput::make('contribution_amount')
                    ->label('Contribution Amount')
                    ->numeric()
                    ->required(),

                Select::make('frequency')
                    ->label('Contribution Frequency')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ])
                    ->required(),

                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('active')
                    ->required(),

                TextInput::make('invite_code')
                    ->label('Invite Code')
                    ->maxLength(6)
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('target_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contribution_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('interest_rate')
                    ->label('Interest Rate')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('frequency'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('invite_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creator')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('members_count')
                    ->label('Members')
                    ->counts('members'),

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

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}

