<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanApplicationResource\Pages;
use App\Models\Loan;
use App\Models\LoanApplication;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoanApplicationResource extends Resource
{
    protected static ?string $model = LoanApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Loan Applications';

    protected static ?string $modelLabel = 'Loan Application';

    protected static ?string $pluralModelLabel = 'Loan Applications';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Application Details')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Member')
                        ->relationship('user', 'name')
                        ->disabled(),
                    Forms\Components\Select::make('group_id')
                        ->label('Group')
                        ->relationship('group', 'name')
                        ->disabled(),
                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->disabled(),
                    Forms\Components\TextInput::make('duration_months')
                        ->label('Duration (Months)')
                        ->numeric()
                        ->disabled(),
                    Forms\Components\DatePicker::make('requested_due_date')
                        ->disabled(),
                    Forms\Components\Textarea::make('reason')
                        ->rows(4)
                        ->columnSpanFull()
                        ->disabled(),
                ])->columns(2),

            Forms\Components\Section::make('Review')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])
                        ->required(),
                    Forms\Components\Select::make('loan_id')
                        ->label('Linked Loan')
                        ->options(fn (callable $get) => Loan::query()
                            ->where('user_id', $get('user_id'))
                            ->where('group_id', $get('group_id'))
                            ->pluck('id', 'id'))
                        ->searchable()
                        ->nullable(),
                    Forms\Components\DateTimePicker::make('reviewed_at')
                        ->default(now()),
                    Forms\Components\Textarea::make('review_notes')
                        ->rows(4)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Group')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_months')
                    ->label('Duration')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (LoanApplication $record): bool => $record->status === 'pending')
                    ->action(function (LoanApplication $record): void {
                        $activeLoan = Loan::query()
                            ->where('group_id', $record->group_id)
                            ->where('user_id', $record->user_id)
                            ->whereIn('status', ['pending', 'approved'])
                            ->first();

                        if ($activeLoan) {
                            Notification::make()
                                ->title('Cannot approve application')
                                ->body('This member already has an active loan in this group.')
                                ->danger()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($record): void {
                            $group = $record->group;
                            $durationMonths = max((int) ($record->duration_months ?? 1), 1);
                            $interestRate = (float) ($group?->interest_rate ?? 0) / 100;
                            $interest = (float) $record->amount * $interestRate * $durationMonths;
                            $totalAmount = (float) $record->amount + $interest;
                            $dueDate = Carbon::now()->addMonths($durationMonths);

                            $loan = Loan::query()->create([
                                'user_id' => $record->user_id,
                                'group_id' => $record->group_id,
                                'amount' => $record->amount,
                                'interest' => $interest,
                                'total_amount' => $totalAmount,
                                'due_date' => $dueDate,
                                'status' => 'approved',
                            ]);

                            $record->update([
                                'status' => 'approved',
                                'loan_id' => $loan->id,
                                'reviewed_at' => now(),
                                'requested_due_date' => $record->requested_due_date ?? $dueDate->toDateString(),
                            ]);
                        });

                        Notification::make()
                            ->title('Loan application approved')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LoanApplication $record): bool => $record->status === 'pending')
                    ->action(function (LoanApplication $record): void {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Loan application rejected')
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListLoanApplications::route('/'),
            'edit' => Pages\EditLoanApplication::route('/{record}/edit'),
        ];
    }
}
