<?php

namespace App\Console\Commands;

use App\Services\Licence\Licence;
use App\Services\Licence\LicenceService;
use Illuminate\Console\Command;

/**
 * Reports the licence state in a form the desktop launcher can grep, mirroring
 * the DESKTOP_OWNER_SETUP_REQUIRED line. Also the natural place for a future
 * online heartbeat once the app phones home.
 */
class CheckLicence extends Command
{
    protected $signature = 'thai2d3d:licence-check';

    protected $description = 'Report the desktop licence status and advance the clock reference';

    public function handle(LicenceService $licences): int
    {
        if (! LicenceService::enforcedHere()) {
            $this->line('LICENCE-STATUS='.Licence::STATE_VALID.' SERVER=1');

            return self::SUCCESS;
        }

        $status = $licences->status();
        $licences->touchLastSeen();

        $this->line(sprintf(
            'LICENCE-STATUS=%s LICENCE-ID=%s LICENCE-EXPIRES=%s LICENCE-DAYS=%s',
            $status->state,
            $status->licence?->licence_id ?? '-',
            $status->expiresOn() ?? '-',
            $status->daysRemaining() ?? '-',
        ));

        if ($status->isLocked()) {
            $this->warn('The desktop copy is in read-only mode: sales, settings and logins stay blocked until a licence is installed.');
        }

        return self::SUCCESS;
    }
}
