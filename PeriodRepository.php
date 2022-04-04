<?php

namespace App\Repositories;

use App\Filters\PeriodFilter;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Class PeriodRepository
 * @package App\Repositories\Mlm
 */
class PeriodRepository
{
    /**
     * @var PeriodFilter
     */
    private $periodFilter;

    /**
     * PeriodRepository constructor.
     * @param PeriodFilter $periodFilter
     */
    public function __construct(PeriodFilter $periodFilter)
    {
        $this->periodFilter = $periodFilter;
    }

    /**
     * @return Builder
     */
    public function query(): Builder
    {
        return Period::query();
    }

    /**
     * @return Builder
     */
    public function sortable(): Builder
    {
        return Period::sortable();
    }

    /**
     * @param array $data
     * @return Period|object
     */
    public function create(array $data): Period
    {
        return $this->query()->create($data);
    }

    /**
     * @param int $id
     * @return Period|object|null
     */
    public function find(int $id): ?Period
    {
        return $this->query()->find($id);
    }

    /**
     * @param array $attributes
     * @param array $data
     * @return Period|object
     */
    public function updateOrCreate(array $attributes, array $data): Period
    {
        return $this->query()->updateOrCreate($attributes, $data);
    }

    /**
     * @return Collection|Period[]
     */
    public function findAll(): Collection
    {
        return $this
            ->query()
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param int $currentId
     * @param int $countSub
     * @return Period|null|object
     */
    public function subPeriodFrom(int $currentId, int $countSub = 1): ?Period
    {
        return $this
            ->query()
            ->where('id', '<=', $currentId)
            ->offset($countSub)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Period|null|object
     */
    public function findLast(): ?Period
    {
        return $this->query()->orderByDesc('id')->first();
    }

    /**
     * @param Carbon $datetime
     * @return int|null
     */
    public function findIdByDate(Carbon $datetime): ?int
    {
        return $this
            ->query()
            ->where('date_from', '<=', $datetime)
            ->where('date_to', '>=', $datetime)
            ->value('id');
    }

    /**
     * @return bool
     */
    public function existsNotClosedOrError(): bool
    {
        return $this
            ->query()
            ->whereNotIn('status', [
                Period::CLOSED_STATUS,
                Period::ERROR_STATUS,
            ])->exists();
    }

    /**
     * @param array $filters
     * @param array $with
     * @return Builder
     */
    public function findByFilterQuery(array $filters, array $with = []): Builder
    {
        return $this
            ->periodFilter
            ->filter($this->sortable(), $filters)
            ->with($with);
    }

    /**
     * @param array $filters
     * @param array $with
     * @return Builder
     */
    public function findByStatusPriority(array $filters, array $with = []): Builder
    {
        $expression = '';
        foreach (Period::STATUSES as $index => $status) {
            $expression .= "WHEN status='$status' THEN $index \n";
        }

        return $this->findByFilterQuery($filters, $with)
            ->orderByRaw("CASE $expression END ASC")
            ->orderByDesc('created_at');
    }
}
