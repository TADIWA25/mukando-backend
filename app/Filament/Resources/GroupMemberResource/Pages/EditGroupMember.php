<?php

namespace App\Filament\Resources\GroupMemberResource\Pages;

use App\Filament\Resources\GroupMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupMember extends EditRecord
{
    protected static string $resource = GroupMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
