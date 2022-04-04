<?php

namespace App\Jobs;

use App\Enums\QueueEnum;
use App\Models\Period;
use App\Repositories\PeriodTempPropertyRepository;
use App\Services\PeriodComputeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Class PeriodComputeJob
 * @package App\Jobs
 */
class PeriodComputeJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Dispatchable;

    /**
     * @var int
     */
    public $timeout = 36000;

    /**
     * @var Period
     */
    private $period;

    /**
     * @param Period $period
     */
    public function __construct(Period $period)
    {
        $this->period = $period;
        $this->onQueue(QueueEnum::PERIOD_COMPUTE_QUEUE);
    }

    /**
     * @param DatabaseManager $databaseManager
     * @param PeriodComputeService $periodComputeService
     * @param PeriodTempPropertyRepository $periodTempPropertyRepository
     * @return void
     * @throws Throwable
     */
    public function handle(
        DatabaseManager $databaseManager,
        PeriodComputeService $periodComputeService,
        PeriodTempPropertyRepository $periodTempPropertyRepository
    ): void {
        ini_set('memory_limit', -1);

        $this->period->update([
            'status' => Period::COMPUTING_STATUS,
        ]);

        try {
            $this->period->setStatusText('Copy to period temp properties');

            $periodTempPropertyRepository->copyToTemp();

            $databaseManager->transaction(function () use ($periodComputeService): void {
                $periodComputeService->compute($this->period);
            });
        } catch (Throwable $exception) {
            $this->period->update([
                'status' => Period::ERROR_STATUS,
            ]);

            throw $exception;
        }
    }
}
