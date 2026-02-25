<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupResource\Pages;
use App\Filament\Resources\GroupResource\RelationManagers;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

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
                ->required()
                ->reactive() // Watch for changes
                ->afterStateUpdated(function ($state, callable $set) {
                    // Clear interest_rate if not shared
                    if ($state !== 'shared') {
                        $set('interest_rate', null);
                    }
                }),

            TextInput::make('contribution_amount')
                ->label('Contribution Amount')
                ->numeric(),

            Select::make('frequency')
                ->label('Contribution Frequency')
                ->options([
                    'weekly' => 'Weekly',
                    'bi-monthly' => 'Bi-Monthly',
                    'monthly' => 'Monthly',
                    'yearly' => 'Yearly',
                ]),

            TextInput::make('interest_rate')
                ->label('Interest Rate (%)')
                ->numeric()
                ->visible(fn ($get) => $get('type') === 'shared') // Show only if shared
                ->required(fn ($get) => $get('type') === 'shared'), // Required only if shared
        ]);
}
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('contribution_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('frequency'),
                Tables\Columns\TextColumn::make('interest_rate')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_by')
                    ->numeric()
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
                    ->counts('members') ,
            
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


    public function members()
{
    return $this->hasMany(GroupMember::class);
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
