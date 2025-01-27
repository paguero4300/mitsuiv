<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auction;
use App\Models\AuctionStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateAuctionStatuses extends Command
{
    protected $signature = 'auctions:update-statuses';
    protected $description = 'Actualiza los estados de las subastas según su tiempo y condiciones';

    public function handle()
    {
        // Configurar zona horaria
        date_default_timezone_set('America/Lima');
        
        $now = now()->timezone('America/Lima');
        
        // 1. Procesar subastas que necesitan finalizar (Sin Oferta o En Proceso)
        $this->processEndingAuctions($now);
        
        // 2. Procesar subastas ganadas que han sido rechazadas
        $this->processRejectedAuctions();
        
        $this->info("Proceso completado.");
    }

    private function processEndingAuctions($now)
    {
        // Obtener subastas que necesitan actualización
        $auctions = Auction::query()
            ->whereIn('status_id', [
                AuctionStatus::SIN_OFERTA,  // 2
                AuctionStatus::EN_PROCESO   // 3
            ])
            ->where('end_date', '<=', $now)
            ->get();

        $processed = 0;
        foreach ($auctions as $auction) {
            DB::beginTransaction();
            try {
                $oldStatus = $auction->status_id;
                $hasBids = $auction->bids()->exists();
                
                if ($hasBids) {
                    // Si hay pujas, la subasta pasa a estado GANADA
                    $auction->status_id = AuctionStatus::GANADA; // 5
                    $this->info("Subasta #{$auction->id} cambió de estado {$oldStatus} a GANADA - Tiene ofertas");
                    Log::info("Subasta #{$auction->id} finalizó con ofertas", [
                        'old_status' => $oldStatus,
                        'new_status' => 'GANADA',
                        'has_bids' => true
                    ]);
                } else {
                    // Si no hay pujas, la subasta pasa a estado FALLIDA
                    $auction->status_id = AuctionStatus::FALLIDA; // 4
                    $this->info("Subasta #{$auction->id} cambió de estado {$oldStatus} a FALLIDA - Sin ofertas");
                    Log::info("Subasta #{$auction->id} finalizó sin ofertas", [
                        'old_status' => $oldStatus,
                        'new_status' => 'FALLIDA',
                        'has_bids' => false
                    ]);
                }
                
                $auction->save();
                DB::commit();
                $processed++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error actualizando estado de subasta #{$auction->id}: " . $e->getMessage());
                $this->error("Error procesando subasta #{$auction->id}: " . $e->getMessage());
            }
        }

        $this->info("Subastas finalizadas procesadas: " . $processed);
    }

    private function processRejectedAuctions()
    {
        // Obtener subastas en estado GANADA que tienen una adjudicación rechazada
        $auctions = Auction::query()
            ->where('status_id', AuctionStatus::GANADA)
            ->whereHas('adjudications', function ($query) {
                $query->where('status', 'rejected');
            })
            ->get();

        $processed = 0;
        foreach ($auctions as $auction) {
            DB::beginTransaction();
            try {
                // Cambiar estado a FALLIDA
                $auction->status_id = AuctionStatus::FALLIDA;
                $auction->save();
                
                $this->info("Subasta #{$auction->id} marcada como FALLIDA - Adjudicación rechazada");
                Log::info("Subasta #{$auction->id} rechazada por el tasador", [
                    'old_status' => 'GANADA',
                    'new_status' => 'FALLIDA',
                    'reason' => 'adjudication_rejected'
                ]);
                
                DB::commit();
                $processed++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error procesando rechazo de subasta #{$auction->id}: " . $e->getMessage());
                $this->error("Error procesando rechazo de subasta #{$auction->id}: " . $e->getMessage());
            }
        }

        $this->info("Subastas rechazadas procesadas: " . $processed);
    }
} 