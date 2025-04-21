<?php

namespace App\Services;

use App\Models\Auction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AuctionExtensionService
{
    /**
     * Verifica si una subasta necesita extensión de tiempo y la extiende si es necesario
     *
     * @param Auction $auction La subasta a verificar
     * @return bool True si la subasta fue extendida, false en caso contrario
     */
    public function extendAuctionIfNeeded(Auction $auction): bool
    {
        // Zona horaria de Lima (definida en el modelo Auction)
        $timezone = 'America/Lima';

        // Obtener la hora actual en la zona horaria correcta
        $now = Carbon::now()->timezone($timezone);
        $endDate = $auction->end_date;

        // Verificar si la subasta está en el último minuto
        if ($auction->isInLastMinute()) {
            // Extender la subasta por 1 minuto desde la hora actual
            $newEndDate = $now->copy()->addMinute();

            Log::info('Extendiendo tiempo de subasta', [
                'auction_id' => $auction->id,
                'old_end_date' => $endDate->format('Y-m-d H:i:s'),
                'new_end_date' => $newEndDate->format('Y-m-d H:i:s'),
                'minutes_remaining' => $minutesRemaining,
                'timezone' => $timezone
            ]);

            // Actualizar la fecha de finalización
            $auction->end_date = $newEndDate;
            $auction->save();

            return true;
        }

        return false;
    }
}
