<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Models\Loan;
use App\Models\Group;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Group Dropdown - Only Shared Funds
                Forms\Components\Select::make('group_id')
                    ->label('Shared Fund Group')
                    ->options(Group::where('type', 'shared')->pluck('name','id'))
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $group = Group::find($state);
                        if ($group) {
                            $interest = $group->interest_rate ?? 0;
                            $set('interest', $interest);
                            
                            $amount = $get('amount') ?? 0;
                            $set('total_amount', $amount + ($amount * $interest / 100));
                        }
                    }),

                // Member Dropdown - Depends on Group
                Forms\Components\Select::make('user_id')
                    ->label('Member')
                    ->options(function (callable $get) {
                        $groupId = $get('group_id');
                        if (!$groupId) return [];
                        return \App\Models\GroupMember::where('group_id', $groupId)
                            ->with('user')
                            ->get()
                            ->pluck('user.name', 'user.id');
                    })
                    ->searchable()
                    ->required(),

                // Amount
                Forms\Components\TextInput::make('amount')
                    ->label('Loan Amount')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $interest = $get('interest') ?? 0;
                        $set('total_amount', $state + ($state * $interest / 100));
                    }),

                // Interest (auto from group)
                Forms\Components\TextInput::make('interest')
                    ->label('Interest %')
                    ->numeric()
                    ->disabled()
                    ->dehydrated() // ensure it saves
                    ->default(fn (callable $get) => optional(Group::find($get('group_id')))->interest_rate ?? 0),

                // Total Amount
                Forms\Components\TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->numeric()
                    ->disabled()
                    ->dehydrated() // ensure it saves
                    ->default(function (callable $get) {
                        $amount = $get('amount') ?? 0;
                        $interest = optional(Group::find($get('group_id')))->interest_rate ?? 0;
                        return $amount + ($amount * $interest / 100);
                    }),

                // Due Date
                Forms\Components\DatePicker::make('due_date')
                    ->label('Due Date')
                    ->required(),

                // Status
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Member')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('group.name')->label('Group')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Loan Amount')->sortable(),
                Tables\Columns\TextColumn::make('interest')->label('Interest %')->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }
}