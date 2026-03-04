<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContributionResource\Pages;
use App\Models\Contribution;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contribution Information')
                ->schema([

                    // 1️⃣ Select Group
                    Select::make('group_id')
                        ->label('Group')
                        ->options(\App\Models\Group::pluck('name', 'id'))
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $group = \App\Models\Group::find($state);
                                if ($group) {
                                    $set('amount_paid', $group->contribution_amount);
                                }
                            } else {
                                $set('amount_paid', null);
                            }
                        })
                        ->required(),

                    // 2️⃣ Select User based on group
                    Select::make('user_id')
                        ->label('Member')
                        ->options(function (callable $get) {
                            $groupId = $get('group_id');

                            if (! $groupId) {
                                return [];
                            }

                            return \App\Models\GroupMember::where('group_id', $groupId)
                                ->with('user')
                                ->get()
                                ->pluck('user.name', 'user.id');
                        })
                        ->searchable()
                        ->required()
                        ->unique(
                            table: 'contributions',
                            column: 'user_id',
                            modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, callable $get) {
                                return $rule->where('group_id', $get('group_id')); // ✅ FIXED
                            },
                            ignoreRecord: true
                        )
                        ->validationMessages([
                            'unique' => 'This member already has a contribution record for this group.',
                        ])
                        ->helperText('A member can only have one contribution record per group.'),

                    // 3️⃣ Contribution amount
                    TextInput::make('amount_paid')
                        ->numeric()
                        ->required()
                        ->minValue(0),

                    // 4️⃣ Paid at
                    Forms\Components\DateTimePicker::make('paid_at')
                        ->default(now())
                        ->required(),

                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Member Name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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