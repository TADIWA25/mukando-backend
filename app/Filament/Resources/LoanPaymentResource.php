<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanPaymentResource\Pages;
use App\Models\Loan;
use App\Models\LoanPayment;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoanPaymentResource extends Resource
{
    protected static ?string $model = LoanPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Loan Payment Information')
                ->schema([

                    // 1️⃣ Select member from existing loans
                    Select::make('user_id')
                        ->label('Member')
                        ->options(
                            Loan::query()
                                ->where('status', '!=', 'paid')
                                ->with('user')
                                ->get()
                                ->unique('user_id')
                                ->mapWithKeys(fn (Loan $loan) => [$loan->user_id => $loan->user?->name ?? "User #{$loan->user_id}"])
                                ->toArray()
                        )
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $set('loan_id', null);
                            $set('amount', null);
                        })
                        ->searchable()
                        ->required()
                        ->dehydrated(false),

                    // 2️⃣ Select loan from loans table
                    Select::make('loan_id')
                        ->label('Loan')
                        ->options(function (callable $get) {
                            $userId = $get('user_id');

                            if (!$userId) {
                                return [];
                            }

                            return Loan::where('user_id', $userId)
                                ->where('status', '!=', 'paid')
                                ->with('group')
                                ->get()
                                ->mapWithKeys(function ($loan) {
                                    $balance = $loan->remaining_balance;
                                    $groupName = $loan->group?->name ?? 'N/A';

                                    return [$loan->id => "Loan #{$loan->id} - {$groupName} - Balance: $" . number_format($balance, 2)];
                                });
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $loan = Loan::find($state);
                                if ($loan) {
                                    // Set the amount to the remaining balance
                                    $set('amount', $loan->remaining_balance);
                                }
                            } else {
                                $set('amount', null);
                            }
                        })
                        ->required(),

                    Hidden::make('amount')
                        ->required()
                        ->default(0),

                    // 5️⃣ Paid at
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
                Tables\Columns\TextColumn::make('loan.user.name')
                    ->label('Member Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan.group.name')
                    ->label('Group')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount Paid')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Date')
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
            'index' => Pages\ListLoanPayments::route('/'),
            'create' => Pages\CreateLoanPayment::route('/create'),
            'edit' => Pages\EditLoanPayment::route('/{record}/edit'),
        ];
    }
}
