<?php

namespace Webkul\Account\Filament\Resources\RefundResource\Pages;

use Webkul\Account\Filament\Resources\RefundResource;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Webkul\Account\Enums;
use Webkul\Account\Enums\DisplayType;
use Webkul\Account\Enums\PaymentState;
use Webkul\Account\Models\MoveLine;
use Webkul\Account\Models\Partner;
use Webkul\Account\Models\Move;
use Webkul\Account\Services\TaxService;
use Webkul\Account\Filament\Resources\InvoiceResource\Pages\CreateInvoice as CreateBaseRefund;

class CreateRefund extends CreateBaseRefund
{
    protected static string $resource = RefundResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('Refund Created'))
            ->body(__('Refund has been created successfully.'));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        $data['creator_id'] = $user->id;
        $data['state'] ??= Enums\MoveState::DRAFT->value;
        $data['move_type'] ??= Enums\MoveType::IN_REFUND->value;
        $data['date'] = now();
        $data['sort'] = Move::max('sort') + 1;
        $data['payment_state'] = PaymentState::NOT_PAID->value;

        if ($data['partner_id']) {
            $partner = Partner::find($data['partner_id']);
            $data['commercial_partner_id'] = $partner->id;
            $data['partner_shipping_id'] = $partner->id;
            $data['invoice_partner_display_name'] = $partner->name;
        } else {
            $data['invoice_partner_display_name'] = "#Created By: {$user->name}";
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        $this->getResource()::collectTotals($record);
    }
}
