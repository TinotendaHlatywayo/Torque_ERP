<?php

namespace App\Console\Commands;

use App\Support\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Release stock holds for expired online reservations';

    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expireDue();
        $this->info("Expired {$count} reservations.");

        return self::SUCCESS;
    }
}
