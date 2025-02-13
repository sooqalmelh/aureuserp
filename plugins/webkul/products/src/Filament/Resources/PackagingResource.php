<?php

namespace Webkul\Product\Filament\Clusters\Configurations\Resources;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Webkul\Product\Models\Packaging;

class PackagingResource extends Resource
{
    protected static ?string $model = Packaging::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('products::filament/resources/packaging.form.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('barcode')
                    ->label(__('products::filament/resources/packaging.form.barcode'))
                    ->maxLength(255),
                Forms\Components\Select::make('product_id')
                    ->label(__('products::filament/resources/packaging.form.product'))
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('qty')
                    ->label(__('products::filament/resources/packaging.form.qty'))
                    ->required()
                    ->numeric()
                    ->minValue(0.00),
                Forms\Components\Select::make('routes')
                    ->label(__('products::filament/resources/packaging.form.routes'))
                    ->relationship('routes', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                Forms\Components\Select::make('company_id')
                    ->label(__('products::filament/resources/packaging.form.company'))
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('products::filament/resources/packaging.table.columns.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('products::filament/resources/packaging.table.columns.product'))
                    ->searchable()
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('products::filament/resources/packaging.table.columns.qty'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('products::filament/resources/packaging.table.columns.barcode'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label(__('products::filament/resources/packaging.table.columns.company'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('products::filament/resources/packaging.table.columns.created-at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('products::filament/resources/packaging.table.columns.updated-at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Tables\Grouping\Group::make('product.name')
                    ->label(__('products::filament/resources/packaging.table.groups.product'))
                    ->collapsible(),
                Tables\Grouping\Group::make('created_at')
                    ->label(__('products::filament/resources/packaging.table.groups.created-at'))
                    ->collapsible(),
                Tables\Grouping\Group::make('updated_at')
                    ->label(__('products::filament/resources/packaging.table.groups.updated-at'))
                    ->date()
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product')
                    ->label(__('products::filament/resources/packaging.table.filters.product'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('products::filament/resources/packaging.table.actions.edit.notification.title'))
                            ->body(__('products::filament/resources/packaging.table.actions.edit.notification.body')),
                    ),
                Tables\Actions\DeleteAction::make()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('products::filament/resources/packaging.table.actions.delete.notification.title'))
                            ->body(__('products::filament/resources/packaging.table.actions.delete.notification.body')),
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('print')
                        ->label(__('products::filament/resources/packaging.table.bulk-actions.print.label'))
                        ->icon('heroicon-o-printer')
                        ->action(function ($records) {
                            $pdf = PDF::loadView('inventories::filament.clusters.configurations.packagings.actions.print', [
                                'records' => $records,
                            ]);

                            $pdf->setPaper('a4', 'portrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'Packaging-Barcode.pdf');
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('products::filament/resources/packaging.table.bulk-actions.delete.notification.title'))
                                ->body(__('products::filament/resources/packaging.table.bulk-actions.delete.notification.body')),
                        ),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('products::filament/resources/packaging.table.empty-state-actions.create.label'))
                    ->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['creator_id'] = Auth::id();

                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('products::filament/resources/packaging.table.empty-state-actions.create.notification.title'))
                            ->body(__('products::filament/resources/packaging.table.empty-state-actions.create.notification.body')),
                    ),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('products::filament/resources/packaging.infolist.sections.general.title'))
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label(__('products::filament/resources/packaging.infolist.sections.general.entries.name'))
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->columnSpan(2)
                            ->icon('heroicon-o-gift'),

                        Infolists\Components\TextEntry::make('barcode')
                            ->label(__('products::filament/resources/packaging.infolist.sections.general.entries.barcode'))
                            ->icon('heroicon-o-bars-4')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('product.name')
                            ->label(__('products::filament/resources/packaging.infolist.sections.general.entries.product'))
                            ->icon('heroicon-o-cube')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('qty')
                            ->label(__('products::filament/resources/packaging.infolist.sections.general.entries.qty'))
                            ->icon('heroicon-o-scale')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('packageType.name')
                            ->label(__('products::filament/resources/packaging.infolist.sections.general.entries.package_type'))
                            ->icon('heroicon-o-archive-box')
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make(__('products::filament/resources/packaging.infolist.sections.routing.title'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('routes')
                            ->label(__('products::filament/resources/packaging.infolist.sections.routing.entries.routes'))
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label(__('products::filament/resources/packaging.infolist.sections.routing.entries.route_name'))
                                    ->icon('heroicon-o-truck'),
                            ])
                            ->placeholder('—')
                            ->columns(1),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make(__('products::filament/resources/packaging.infolist.sections.organization.title'))
                    ->schema([
                        Infolists\Components\TextEntry::make('company.name')
                            ->label(__('products::filament/resources/packaging.infolist.sections.organization.entries.company'))
                            ->icon('heroicon-o-building-office')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('creator.name')
                            ->label(__('products::filament/resources/packaging.infolist.sections.organization.entries.creator'))
                            ->icon('heroicon-o-user')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('products::filament/resources/packaging.infolist.sections.organization.entries.created_at'))
                            ->dateTime()
                            ->icon('heroicon-o-calendar')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label(__('products::filament/resources/packaging.infolist.sections.organization.entries.updated_at'))
                            ->dateTime()
                            ->icon('heroicon-o-clock')
                            ->placeholder('—'),
                    ])
                    ->collapsible()
                    ->columns(2),
            ]);
    }
}
