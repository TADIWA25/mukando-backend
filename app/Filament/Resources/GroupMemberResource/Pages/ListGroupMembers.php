<?php

namespace App\Filament\Resources\GroupMemberResource\Pages;

use App\Filament\Resources\GroupMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupMembers extends ListRecords
{
    protected static string $resource = GroupMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
