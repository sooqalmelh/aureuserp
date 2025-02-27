<?php

namespace Webkul\Account\Filament\Resources\InvoiceResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Webkul\Account\Enums\DisplayType;
use Webkul\Account\Filament\Resources\InvoiceResource;
use Webkul\Account\Filament\Resources\InvoiceResource\Actions as BaseActions;
use Webkul\Account\Models\MoveLine;
use Webkul\Partner\Models\Partner;
use Webkul\Chatter\Filament\Actions as ChatterActions;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('accounts::filament/resources/invoice/pages/edit-invoice.notification.title'))
            ->body(__('accounts::filament/resources/invoice/pages/edit-invoice.notification.body'));
    }

    protected function getHeaderActions(): array
    {
        return [
            ChatterActions\ChatterAction::make()
                ->setResource($this->getResource()),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            BaseActions\PayAction::make(),
            BaseActions\ConfirmAction::make(),
            BaseActions\CancelAction::make(),
            BaseActions\ResetToDraftAction::make(),
            BaseActions\SetAsCheckedAction::make(),
            BaseActions\PreviewAction::make(),
            BaseActions\PrintAndSendAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();

        $record = $this->getRecord();

        $data['partner_id'] ??= $record->partner_id;
        $data['invoice_date'] ??= $record->invoice_date;
        $data['name'] ??= $record->name;
        $data['auto_post'] ??= $record->auto_post;

        if ($data['partner_id']) {
            $partner = Partner::find($data['partner_id']);

            $data['invoice_partner_display_name'] = $partner->name;
        } else {
            $data['invoice_partner_display_name'] = "#Created By: {$user->name}";
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        $this->getResource()::collectTotals($record);

        $this->updateOrCreatePaymentTermLine($record);

        $this->updateOrCreateTaxLine($record);
    }

    private function updateOrCreatePaymentTermLine($record): void
    {
        $paymentTermLine = MoveLine::where('move_id', $record->id)
            ->where('display_type', 'payment_term')
            ->first();

        if ($record->invoicePaymentTerm && $record->invoicePaymentTerm?->dueTerm?->nb_days) {
            $dateMaturity = $record->invoice_date_due->addDays($record->invoicePaymentTerm->dueTerm->nb_days);
        } else {
            $dateMaturity = $record->invoice_date_due;
        }

        if ($paymentTermLine) {
            $paymentTermLine->update([
                'currency_id'           => $record->currency_id,
                'partner_id'            => $record->partner_id,
                'date_maturity'         => $dateMaturity,
                'company_id'            => $record->company_id,
                'company_currency_id'   => $record->company_currency_id,
                'commercial_partner_id' => $record->partner_id,
                'parent_state'          => $record->state,
                'debit'                 => $record->amount_total,
                'balance'               => $record->amount_total,
                'amount_currency'       => $record->amount_total,
            ]);
        } else {
            MoveLine::create([
                'move_id'               => $record->id,
                'move_name'             => $record->name,
                'display_type'          => 'payment_term',
                'currency_id'           => $record->currency_id,
                'partner_id'            => $record->partner_id,
                'date_maturity'         => $dateMaturity,
                'company_id'            => $record->company_id,
                'company_currency_id'   => $record->company_currency_id,
                'commercial_partner_id' => $record->partner_id,
                'sort'                  => MoveLine::max('sort') + 1,
                'parent_state'          => $record->state,
                'date'                  => now(),
                'creator_id'            => $record->creator_id,
                'debit'                 => $record->amount_total,
                'balance'               => $record->amount_total,
                'amount_currency'       => $record->amount_total,
            ]);
        }
    }

    private function updateOrCreateTaxLine($record): void
    {
        $lines = $record->lines->where('display_type', DisplayType::PRODUCT->value);
        $existingTaxLines = MoveLine::where('move_id', $record->id)->where('display_type', 'tax')->get()->keyBy('tax_line_id');
        $newTaxEntries = [];

        foreach ($lines as $line) {
            if ($line->taxes->isEmpty()) {
                continue;
            }

            $taxes = $line->taxes()->orderBy('sort')->get();
            $priceUnit = $line->price_unit;
            $quantity = $line->quantity;
            $baseAmount = $line->price_subtotal;
            $subTotal = $priceUnit * $quantity;
            $discountValue = floatval($line->discount ?? 0);

            if ($discountValue > 0) {
                $discountAmount = $subTotal * ($discountValue / 100);
                $subTotal -= $discountAmount;
            }

            $taxesComputed = [];

            foreach ($taxes as $tax) {
                $amount = floatval($tax->amount);
                $currentTaxBase = $baseAmount;
                $tax->price_include_override ??= 'tax_excluded';

                if ($tax->is_base_affected) {
                    foreach ($taxesComputed as $prevTax) {
                        if ($prevTax['include_base_amount']) {
                            $currentTaxBase += $prevTax['tax_amount'];
                        }
                    }
                }

                $currentTaxAmount = 0;

                if ($tax->price_include_override == 'tax_included') {
                    $taxFactor = ($tax->amount_type == 'percent') ? $amount / 100 : $amount;
                    $currentTaxAmount = $currentTaxBase - ($currentTaxBase / (1 + $taxFactor));

                    if (empty($taxesComputed)) {
                        $priceUnit -= ($currentTaxAmount / $quantity);
                        $subTotal = $priceUnit * $quantity;
                        $baseAmount = $subTotal;
                    }
                } elseif ($tax->price_include_override == 'tax_excluded') {
                    $currentTaxAmount = ($tax->amount_type == 'percent') ? ($currentTaxBase * $amount / 100) : ($amount * $quantity);
                }

                if (isset($newTaxEntries[$tax->id])) {
                    $newTaxEntries[$tax->id]['debit'] += $currentTaxAmount;
                    $newTaxEntries[$tax->id]['credit'] += $currentTaxAmount;
                    $newTaxEntries[$tax->id]['balance'] -= $currentTaxAmount;
                    $newTaxEntries[$tax->id]['amount_currency'] -= $currentTaxAmount;
                } else {
                    $newTaxEntries[$tax->id] = [
                        'name' => $tax->name,
                        'move_id' => $record->id,
                        'move_name' => $record->name,
                        'display_type' => 'tax',
                        'currency_id' => $record->currency_id,
                        'partner_id' => $record->partner_id,
                        'company_id' => $record->company_id,
                        'company_currency_id' => $record->company_currency_id,
                        'commercial_partner_id' => $record->partner_id,
                        'parent_state' => $record->state,
                        'date' => now(),
                        'creator_id' => $record->creator_id,
                        'debit' => $currentTaxAmount,
                        'credit' => $currentTaxAmount,
                        'balance' => -$currentTaxAmount,
                        'amount_currency' => -$currentTaxAmount,
                        'tax_base_amount' => $currentTaxBase,
                        'tax_line_id' => $tax->id,
                        'tax_group_id' => $tax->tax_group_id,
                    ];
                }

                $taxesComputed[] = [
                    'tax_id' => $tax->id,
                    'tax_amount' => $currentTaxAmount,
                    'include_base_amount' => $tax->include_base_amount,
                ];
            }
        }

        foreach ($newTaxEntries as $taxId => $taxData) {
            if (isset($existingTaxLines[$taxId])) {
                $existingTaxLines[$taxId]->update($taxData);
                unset($existingTaxLines[$taxId]);
            } else {
                $taxData['sort'] = MoveLine::max('sort') + 1;
                MoveLine::create($taxData);
            }
        }

        foreach ($existingTaxLines as $oldTaxLine) {
            $oldTaxLine->delete();
        }
    }
}
