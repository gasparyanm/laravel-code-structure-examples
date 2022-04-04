<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Class RequestDocumentFilter
 * @package App\Filters\Admin
 */
class PeriodFilter
{
    /**
     * @param Builder $builder
     * @param string[] $filters
     * @return Builder
     */
    public function filter(Builder $builder, array $filters): Builder
    {
        if (isset($filters['period_at_from']) && !empty($filters['period_at_from'])) {
            $builder->where(new Expression('CAST(periods.date_from as DATE)'), '>=', $filters['period_at_from']);
        }

        if (isset($filters['period_at_to']) && !empty($filters['period_at_to'])) {
            $builder->where(new Expression('CAST(periods.date_to as DATE)'), '<=', $filters['period_at_to']);
        }

        if (isset($filters['created_at_from']) && !empty($filters['created_at_from'])) {
            $builder->where('periods.created_at', '>=', $filters['created_at_from']);
        }

        if (isset($filters['created_at_to']) && !empty($filters['created_at_to'])) {
            $builder->where('periods.created_at', '<=', $filters['created_at_to']);
        }

        if (isset($filters['number']) && !empty($filters['number'])) {
            $number = mb_strtolower($filters['number']);
            $builder->where(new Expression('LOWER(periods.number)'), 'LIKE', "%{$number}%");
        }

        if (isset($filters['statuses']) && !empty($filters['statuses'])) {
            $builder->whereIn('periods.status', $filters['statuses']);
        }

        return $builder;
    }
}
