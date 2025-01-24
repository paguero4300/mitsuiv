<?php

namespace App\Filament\Resources\ResellerAuctionResource\Pages;

use App\Filament\Resources\ResellerAuctionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use App\Services\BidService;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Bid;
use Illuminate\Support\Facades\DB;

class ViewResellerAuction extends ViewRecord
{
    protected static string $resource = ResellerAuctionResource::class;

    public function mount(string|int $record): void
    {
        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bid')
                ->label('Pujar')
                ->icon('heroicon-o-currency-dollar')
                ->visible(fn () => $this->record->canBid())
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Monto a Pujar')
                        ->required()
                        ->numeric()
                        ->minValue(function () {
                            $currentPrice = $this->record->current_price ?? $this->record->base_price;
                            return $currentPrice + $this->record->getMinimumBidIncrement();
                        })
                        ->prefix('USD')
                        ->hint(function () {
                            $currentPrice = $this->record->current_price ?? $this->record->base_price;
                            $increment = $this->record->getMinimumBidIncrement();
                            return sprintf(
                                'Precio actual: USD %s (incremento mínimo: USD %s)',
                                number_format($currentPrice, 2),
                                number_format($increment, 2)
                            );
                        }),
                ])
                ->modalWidth('md')
                ->beforeFormFilled(function () {
                    // Ocultar la galería cuando se abre el modal
                    $this->dispatch('hide-gallery');
                })
                ->after(function () {
                    // Mostrar la galería cuando se cierra el modal
                    $this->dispatch('show-gallery');
                })
                ->action(function (array $data, BidService $bidService): void {
                    try {
                        $bidService->placeBid($this->record, Auth::user(), $data['amount']);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Puja realizada exitosamente')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error al realizar la puja')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Realizar Puja')
                ->modalDescription(fn () => "¿Estás seguro de realizar esta puja por el vehículo {$this->record->vehicle->plate}?")
                ->modalSubmitActionLabel('Confirmar Puja'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        try {
            if ($this->record && $this->record->vehicle) {
                Log::error('Vehicle Debug Data:', [
                    'auction_id' => $this->record->id,
                    'vehicle' => [
                        'id' => $this->record->vehicle->id,
                        'relations' => [
                            'transmission' => $this->record->vehicle->transmission?->value ?? null,
                            'bodyType' => $this->record->vehicle->bodyType?->value ?? null,
                            'traction' => $this->record->vehicle->traction?->value ?? null,
                            'cylinders' => $this->record->vehicle->cylinders?->value ?? null,
                            'fuelType' => $this->record->vehicle->fuelType?->value ?? null,
                            'doors' => $this->record->vehicle->doors?->value ?? null,
                            'color' => $this->record->vehicle->color?->value ?? null,
                            'location' => $this->record->vehicle->location?->value ?? null,
                        ]
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error logging vehicle data: ' . $e->getMessage());
        }

        $data['current_bid'] = $this->record->getUserBid()?->amount;
        $data['is_leading'] = $this->record->bid_status === 'Puja Líder';
        
        return $data;
    }
}
