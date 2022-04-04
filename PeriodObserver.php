<?php

namespace App\Observers;

use App\Models\Period;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;

/**
 * Class PeriodObserver
 * @package App\Observers
 */
class PeriodObserver
{
    /**
     * @var StatefulGuard
     */
    private $guard;

    /**
     * @param StatefulGuard $guard
     */
    public function __construct(Guard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * @param Period $period
     * @return void
     */
    public function creating(Period $period): void
    {
        $period->fill([
            'status' => Period::OPEN_STATUS,
        ]);
    }

    /**
     * @param Period $period
     * @return void
     */
    public function created(Period $period): void
    {
        $period->statusLogs()->create([
            'status_from' => $period->status,
            'status_to'   => $period->status,
            'user_id'     => $this->guard->user()->id ?? null,
        ]);
    }

    /**
     * @param Period $period
     * @return void
     */
    public function updated(Period $period): void
    {
        if (($oldStatus = $period->getOriginal('status')) !== $period->status) {
            $period->statusLogs()->create([
                'status_from' => $oldStatus,
                'status_to'   => $period->status,
                'user_id'     => $this->guard->user()->id ?? null,
            ]);
        }
    }
}
