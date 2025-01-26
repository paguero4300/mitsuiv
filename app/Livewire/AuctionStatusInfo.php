<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Auction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class AuctionStatusInfo extends Component
{
    public Auction $record;
    public $adjudicationNotes = '';
    
    public function mount()
    {
        $this->record->load(['status', 'bids.reseller']);
    }

    public function render()
    {
        return view('livewire.auction-status-info', [
            'startCountdown' => $this->getStartCountdown(),
            'remainingTime' => $this->getRemainingTime(),
            'status' => $this->record->status->name,
            'statusColor' => $this->getStatusColor(),
            'basePrice' => $this->record->base_price,
            'currentPrice' => $this->record->current_price,
            'canAdjudicate' => $this->canAdjudicate(),
        ]);
    }

    public function acceptAdjudication()
    {
        if (!$this->canAdjudicate()) {
            return;
        }

        try {
            DB::beginTransaction();

            // Obtener la mejor oferta
            $winningBid = $this->record->bids()->orderByDesc('amount')->first();
            
            if (!$winningBid) {
                throw new \Exception('No hay ofertas para adjudicar');
            }

            // Crear el registro de adjudicación
            $adjudication = new \App\Models\AuctionAdjudication([
                'reseller_id' => $winningBid->reseller_id,
                'status' => 'accepted',
                'notes' => $this->adjudicationNotes,
            ]);
            
            $this->record->adjudications()->save($adjudication);

            // Actualizar estado de la subasta
            $adjudicatedStatus = \App\Models\AuctionStatus::where('slug', 'adjudicada')->first();
            $this->record->update([
                'status_id' => $adjudicatedStatus->id
            ]);

            DB::commit();

            // Notificar al usuario
            Notification::make()
                ->title('Subasta Adjudicada')
                ->success()
                ->send();

            // TODO: Enviar notificación al revendedor
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->title('Error al adjudicar')
                ->danger()
                ->body('No se pudo completar la adjudicación: ' . $e->getMessage())
                ->send();
        }
    }

    public function rejectAdjudication()
    {
        if (!$this->canAdjudicate()) {
            return;
        }

        try {
            DB::beginTransaction();

            // Obtener la mejor oferta
            $winningBid = $this->record->bids()->orderByDesc('amount')->first();
            
            if (!$winningBid) {
                throw new \Exception('No hay ofertas para rechazar');
            }

            // Crear el registro de adjudicación
            $adjudication = new \App\Models\AuctionAdjudication([
                'reseller_id' => $winningBid->reseller_id,
                'status' => 'rejected',
                'notes' => $this->adjudicationNotes,
            ]);
            
            $this->record->adjudications()->save($adjudication);

            // Actualizar estado de la subasta
            $failedStatus = \App\Models\AuctionStatus::where('slug', 'fallida')->first();
            $this->record->update([
                'status_id' => $failedStatus->id
            ]);

            DB::commit();

            // Notificar al usuario
            Notification::make()
                ->title('Subasta No Adjudicada')
                ->warning()
                ->send();

            // TODO: Enviar notificación al revendedor
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->title('Error al rechazar')
                ->danger()
                ->body('No se pudo completar el rechazo: ' . $e->getMessage())
                ->send();
        }
    }

    private function canAdjudicate(): bool
    {
        // Verificar si el usuario es tasador o admin
        $user = Auth::user();
        if (!$user) return false;

        // Si es super_admin o administrador, puede adjudicar cualquier subasta
        if ($user->roles->pluck('name')->intersect(['super_admin', 'administrador'])->isNotEmpty()) {
            return $this->record->end_date->isPast() && 
                   $this->record->status->slug === 'ganada';
        }

        // Si es tasador, solo puede adjudicar sus propias subastas
        if ($user->roles->pluck('name')->contains('tasador')) {
            return $this->record->end_date->isPast() && 
                   $this->record->status->slug === 'ganada' &&
                   $this->record->appraiser_id === $user->id;
        }

        return false;
    }

    private function getStartCountdown()
    {
        $now = now()->timezone('America/Lima');
        $start = Carbon::parse($this->record->start_date)->timezone('America/Lima');

        if ($start->lte($now)) {
            return null;
        }

        $interval = $now->diff($start);
        $parts = [];

        if ($interval->d > 0) {
            $parts[] = "{$interval->d}d";
        }
        if ($interval->h > 0) {
            $parts[] = "{$interval->h}h";
        }
        if ($interval->i > 0) {
            $parts[] = "{$interval->i}m";
        }
        if ($interval->s > 0 || empty($parts)) {
            $parts[] = "{$interval->s}s";
        }

        return "Inicia en: " . implode(' ', $parts);
    }

    private function getRemainingTime()
    {
        $now = now()->timezone('America/Lima');
        $start = Carbon::parse($this->record->start_date)->timezone('America/Lima');
        $end = Carbon::parse($this->record->end_date)->timezone('America/Lima');

        if ($start->gt($now)) {
            return null;
        }

        if ($end->isPast()) {
            return 'Subasta finalizada';
        }

        $interval = $now->diff($end);
        $parts = [];

        if ($interval->d > 0) {
            $parts[] = "{$interval->d}d";
        }
        if ($interval->h > 0) {
            $parts[] = "{$interval->h}h";
        }
        if ($interval->i > 0) {
            $parts[] = "{$interval->i}m";
        }
        if ($interval->s > 0 || empty($parts)) {
            $parts[] = "{$interval->s}s";
        }

        return implode(' ', $parts);
    }

    private function getStatusColor(): string
    {
        return match ($this->record->status->name) {
            'Sin Oferta' => 'danger',
            'En Proceso' => 'warning',
            'Finalizada' => 'success',
            default => 'gray'
        };
    }
}
