<?php

namespace App\Services;

use App\Enums\QueueEnum;
use App\Exceptions\ExistsNotClosedOrErrorException;
use App\Exceptions\PeriodComputedException;
use App\Exceptions\PeriodResetException;
use App\Exceptions\PeriodSubmittedException;
use App\Jobs\PeriodComputeJob;
use App\Jobs\PeriodSubmitJob;
use App\Models\Period;
use App\Models\Setting;
use App\Repositories\PeriodRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Throwable;

/**
 * Class PeriodService
 * @package App\Services
 */
class PeriodService
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var WorkerService
     */
    private $workerService;

    /**
     * @var SettingService
     */
    private $settingService;

    /**
     * @var PeriodRepository
     */
    private $periodRepository;

    /**
     * @param Dispatcher $dispatcher
     * @param WorkerService $workerService
     * @param SettingService $settingService
     * @param PeriodRepository $periodRepository
     */
    public function __construct(
        Dispatcher $dispatcher,
        WorkerService $workerService,
        SettingService $settingService,
        PeriodRepository $periodRepository
    ) {
        $this->dispatcher = $dispatcher;
        $this->workerService = $workerService;
        $this->settingService = $settingService;
        $this->periodRepository = $periodRepository;
    }

    /**
     * @param Period $period
     * @return Period
     * @throws Throwable
     */
    public function compute(Period $period): Period
    {
        if ($period->status !== Period::OPEN_STATUS) {
            throw new PeriodComputedException('Period must be open!');
        }

        $this->workerService->toggleQueues(false, QueueEnum::ON_PERIOD_CALC_DISABLE);
        $this->dispatcher->dispatch(new PeriodComputeJob($period));

        $this->settingService->updateValue(Setting::PERIOD_COMPUTING_ALIAS, Setting::TRUE_VALUE);

        $period->update([
            'status' => Period::PENDING_STATUS,
        ]);

        return $period;
    }

    /**
     * @param Period $period
     * @return Period
     */
    public function submit(Period $period): Period
    {
        if ($period->status !== Period::REVIEW_STATUS) {
            throw new PeriodSubmittedException('Period must be in review!');
        }

        $this->dispatcher->dispatch(new PeriodSubmitJob($period));

        $period->update([
            'status' => Period::SUBMITTED_STATUS,
        ]);

        return $period;
    }

    /**
     * @param Period $period
     * @return Period
     */
    public function reset(Period $period): Period
    {
        if ($period->status !== Period::REVIEW_STATUS) {
            throw new PeriodResetException('Period must be in review!');
        }

        $period->tempTransactions()->delete();
        $period->update([
            'status' => Period::OPEN_STATUS,
        ]);

        return $period;
    }

    /**
     * @param array $data
     * @return Period
     * @throws Throwable
     */
    public function create(array $data): Period
    {
        if ($this->periodRepository->existsNotClosedOrError()) {
            throw new ExistsNotClosedOrErrorException('Close opening periods first!');
        }

        $this->settingService->updateValue(Setting::PERIOD_COMPUTING_ALIAS, Setting::FALSE_VALUE);
        $this->settingService->updateValue(Setting::TO_ENABLE_WORKERS_ALIAS, Setting::TRUE_VALUE);

        return $this->periodRepository->create($data);
    }

    /**
     * @param Period $period
     * @return Period
     * @throws Throwable
     */
    public function createNextPeriod(Period $period): Period
    {
        $periodDuration = $this->settingService->getSettingInt(Setting::PERIOD_DURATION_IN_DAYS_ALIAS, 7);

        return $this->create([
            'number'    => sprintf('Period - %s', $period->id + 1),
            'date_from' => $period->date_to->toDayDateTimeString(),
            'date_to'   => $period->date_to->clone()->addDays($periodDuration)->toDayDateTimeString(),
            'status'    => Period::OPEN_STATUS,
        ]);
    }
}
