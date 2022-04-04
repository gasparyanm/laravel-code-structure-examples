<?php

namespace App\Jobs;

use App\Enums\QueueEnum;
use App\Models\Period;
use App\Services\PeriodSubmitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Class PeriodSubmitJob
 * @package App\Jobs
 */
class PeriodSubmitJob implements ShouldQueue
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
     * @param PeriodSubmitService $periodSubmitService
     * @return void
     * @throws Throwable
     */
    public function handle(DatabaseManager $databaseManager, PeriodSubmitService $periodSubmitService): void
    {
        ini_set('memory_limit', -1);

        $this->period->update([
            'status' => Period::CLOSING_STATUS,
        ]);

        try {
            $databaseManager->transaction(function () use ($periodSubmitService): Period {
                return $periodSubmitService->submit($this->period);
            });
        } catch (Throwable $exception) {
            $this->period->update([
                'status' => Period::ERROR_STATUS,
            ]);

            throw $exception;
        }
    }
}
