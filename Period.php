<?php

namespace App\Models;

use App\Observers\PeriodObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Kyslik\ColumnSortable\Sortable;

/**
 * Class Period
 * @package App\Models\Mlm
 * @property-read int $id
 * @property string $number
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property string $status
 * @property string $fullPeriod
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read bool $is_closed
 *
 * @property PeriodProperty[]|Collection $properties
 * @property PeriodStatusLog[]|Collection $statusLogs
 * @property Transaction[]|Collection $transactions
 * @property TempTransaction[]|Collection $tempTransactions
 * @property PeriodPool[]|Collection $periodPools
 * @method static Builder sortable($defaultParams = null)
 */
class Period extends Model
{
    use Sortable;

    /**
     * @var int
     */
    public const STATUS_CACHE_TTL = 1000;

    /**
     * @var string
     */
    public const STATUS_CACHE_KEY = 'status_cache_%s';

    /**
     * @var string
     */
    public const OPEN_STATUS = 'open';

    /**
     * @var string
     */
    public const PENDING_STATUS = 'pending';

    /**
     * @var string
     */
    public const COMPUTING_STATUS = 'computing';

    /**
     * @var string
     */
    public const ERROR_STATUS = 'error';

    /**
     * @var string
     */
    public const REVIEW_STATUS = 'review';

    /**
     * @var string
     */
    public const CANCELED_STATUS = 'canceled';

    /**
     * @var string
     */
    public const SUBMITTED_STATUS = 'submitted';

    /**
     * @var string
     */
    public const CLOSING_STATUS = 'closing';

    /**
     * @var string
     */
    public const CLOSED_STATUS = 'closed';

    /**
     * @var string[]
     */
    public const STATUSES = [
        self::OPEN_STATUS,
        self::PENDING_STATUS,
        self::COMPUTING_STATUS,
        self::REVIEW_STATUS,
        self::SUBMITTED_STATUS,
        self::CLOSING_STATUS,
        self::CANCELED_STATUS,
        self::ERROR_STATUS,
        self::CLOSED_STATUS,
    ];

    public static function boot(): void
    {
        parent::boot();
        static::observe([PeriodObserver::class]);
    }

    /**
     * @var string[]
     */
    protected $fillable = [
        'id',
        'number',
        'date_from',
        'date_to',
        'status',
    ];

    /**
     * @var string[]
     */
    protected $dates = [
        'date_from',
        'date_to',
        'created_at',
        'updated_at',
    ];

    /**
     * @return HasMany
     */
    public function properties(): HasMany
    {
        return $this->hasMany(PeriodProperty::class, 'period_id');
    }

    /**
     * @return HasMany
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(PeriodStatusLog::class, 'period_id')->orderByDesc('id');
    }

    /**
     * @return HasMany
     */
    public function tempTransactions(): HasMany
    {
        return $this->hasMany(TempTransaction::class, 'period_id');
    }

    /**
     * @return HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'period_id');
    }

    /**
     * @return HasMany
     */
    public function periodPools(): HasMany
    {
        return $this->hasMany(PeriodPool::class, 'period_id');
    }

    /**
     * @param string $text
     * @return void
     */
    public function setStatusText(string $text): void
    {
        Cache::put(sprintf(self::STATUS_CACHE_KEY, $this->id), $text, self::STATUS_CACHE_TTL);
    }

    /**
     * @return string|null
     */
    public function getStatusText(): ?string
    {
        return Cache::get(sprintf(self::STATUS_CACHE_KEY, $this->id));
    }

    /**
     * @return bool
     */
    public function getIsClosedAttribute(): bool
    {
        return in_array($this->status, [self::ERROR_STATUS, self::CLOSED_STATUS], true);
    }

    /**
     * @return string|null
     */
    public function getFullPeriodAttribute(): ?string
    {
        $dateFrom = $this->date_from;
        $dateTo = $this->date_to;

        if ($dateFrom !== null && $dateTo !== null) {
            return $dateFrom->toDateString() . ' - ' . $dateTo->toDateString();
        }

        return $dateFrom->toDateString() ?? $dateTo->toDateString() ?? null;
    }
}
