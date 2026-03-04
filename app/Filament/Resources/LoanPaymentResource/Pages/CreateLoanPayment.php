<?php

namespace App\Filament\Resources\LoanPaymentResource\Pages;

use App\Filament\Resources\LoanPaymentResource;
use App\Models\Loan;
use Filament\Resources\Pages\CreateRecord;

class CreateLoanPayment extends CreateRecord
{
    protected static string $resource = LoanPaymentResource::class;

    protected function afterCreate(): void
    {
        $loan = Loan::find($this->record->loan_id);

        if ($loan && $loan->remaining_balance <= 0) {
            $loan->update(['status' => 'paid']);
        }
    }
}
