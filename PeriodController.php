<?php

namespace App\Http\Controllers;

use App\Enums\PaginationEnum;
use App\Enums\WithEnums;
use App\Events\UserLogEvent;
use App\Exceptions\ExistsNotClosedOrErrorException;
use App\Exceptions\PeriodComputedException;
use App\Exceptions\PeriodResetException;
use App\Exceptions\PeriodSubmittedException;
use App\Export\Excel\CurrentPropertiesExcel;
use App\Export\Excel\ExportExcelInterface;
use App\Export\Excel\TempTransactionsExcel;
use App\Http\Requests\Admin\Period\StoreFormRequest;
use App\Models\Period;
use App\Models\UserLog;
use App\Repositories\AccountPropertyRepository;
use App\Repositories\PeriodRepository;
use App\Repositories\PeriodTempPropertyRepository;
use App\Services\ExportService;
use App\Services\PeriodService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\DatabaseManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Throwable;

/**
 * Class PeriodController
 * @package App\Http\Controllers\Admin
 */
class PeriodController
{
    /**
     * @var string
     */
    public const MAIN_TAB = 'main';

    /**
     * @var string
     */
    public const POOLS_TAB = 'pools';

    /**
     * @var string
     */
    public const PROPERTIES_TAB = 'properties';

    /**
     * @var string
     */
    public const TRANSACTIONS_TAB = 'transactions';

    /**
     * @var Factory
     */
    private $view;

    /**
     * @var Redirector
     */
    private $redirector;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var PeriodService
     */
    private $periodService;

    /**
     * @var PeriodRepository
     */
    private $periodRepository;

    /**
     * @var AccountPropertyRepository
     */
    private $accountPropertyRepository;

    /**
     * @var PeriodTempPropertyRepository
     */
    private $periodTempPropertyRepository;

    /**
     * @param Factory $view
     * @param Redirector $redirector
     * @param Dispatcher $dispatcher
     * @param PeriodService $periodService
     * @param PeriodRepository $periodRepository
     * @param AccountPropertyRepository $accountPropertyRepository
     * @param PeriodTempPropertyRepository $periodTempPropertyRepository
     */
    public function __construct(
        Factory $view,
        Redirector $redirector,
        Dispatcher $dispatcher,
        PeriodService $periodService,
        PeriodRepository $periodRepository,
        AccountPropertyRepository $accountPropertyRepository,
        PeriodTempPropertyRepository $periodTempPropertyRepository
    ) {
        $this->view = $view;
        $this->redirector = $redirector;
        $this->dispatcher = $dispatcher;
        $this->periodService = $periodService;
        $this->periodRepository = $periodRepository;
        $this->accountPropertyRepository = $accountPropertyRepository;
        $this->periodTempPropertyRepository = $periodTempPropertyRepository;
    }

    /**
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $filters = (array) $request->query->get('filter', []);

        $periods = $this
            ->periodRepository
            ->findByStatusPriority($filters)
            ->get();

        return $this->view->make('admin.periods.index', [
            'periods'                   => $periods,
            'existsNotClosedOrError'    => $this->periodRepository->existsNotClosedOrError(),
            'filters'                   => $filters,
        ]);
    }

    /**
     * @param Period $period
     * @param Request $request
     * @return View
     */
    public function show(Period $period, Request $request): View
    {
        $period->load(['statusLogs.user', 'periodPools.pool']);

        if ($period->status === Period::REVIEW_STATUS) {
            $propertiesQuery = $this->periodTempPropertyRepository->query();
        } elseif ($period->is_closed === false) {
            $propertiesQuery = $this->accountPropertyRepository->query();
        } else {
            $propertiesQuery = $period->properties();
        }

        if ($period->status === Period::REVIEW_STATUS) {
            $transactionsQuery = $period->tempTransactions();
        } elseif ($period->status === Period::CLOSED_STATUS) {
            $transactionsQuery = $period->transactions();
        }

        $properties = $propertiesQuery
            ->with(['account'])
            ->orderBy('account_id')
            ->paginate(PaginationEnum::DEFAULT, ['*'], 'currentPropertiesPage')
            ->appends($request->query->all())
            ->appends('tab', self::PROPERTIES_TAB);

        $transactions = null;
        if (isset($transactionsQuery)) {
            $transactions = $transactionsQuery
                ->with(['account'])
                ->orderBy('id')
                ->paginate(PaginationEnum::DEFAULT, ['*'], 'tempTransactionsPage')
                ->appends($request->query->all())
                ->appends('tab', self::TRANSACTIONS_TAB);
        }

        return $this->view->make('admin.periods.show', [
            'period'       => $period,
            'tab'          => $request->query->get('tab', self::MAIN_TAB),
            'properties'   => $properties,
            'transactions' => $transactions,
        ]);
    }

    /**
     * @return View
     */
    public function create(): View
    {
        return $this->view->make('admin.periods.create');
    }

    /**
     * @param StoreFormRequest $request
     * @return RedirectResponse
     * @throws Throwable
     */
    public function store(StoreFormRequest $request): RedirectResponse
    {
        try {
            $period = $this->periodService->create($request->validated());

            return $this
                ->redirector
                ->route('admin.periods.show', ['period' => $period->id])
                ->with(WithEnums::MESSAGE_WITH, 'Period created!');
        } catch (ExistsNotClosedOrErrorException $exception) {
            return $this
                ->redirector
                ->route('admin.periods.index')
                ->with(WithEnums::ERROR_WITH, $exception->getMessage());
        }
    }

    /**
     * @param Period $period
     * @param Request $request
     * @return RedirectResponse
     * @throws Throwable
     */
    public function compute(Period $period, Request $request): RedirectResponse
    {
        $redirect = $this->redirector->route('admin.periods.show', ['period' => $period->id]);

        try {
            $this->dispatcher->dispatch(new UserLogEvent(
                $request->user(),
                UserLog::COMPUTE_EVENT,
                'Period compute'
            ));

            $this->periodService->compute($period);

            return $redirect->with(WithEnums::MESSAGE_WITH, 'Period will be compute!');
        } catch (PeriodComputedException $exception) {
            return $redirect->with(WithEnums::ERROR_WITH, $exception->getMessage());
        }
    }

    /**
     * @param Period $period
     * @param Request $request
     * @return RedirectResponse
     */
    public function submit(Period $period, Request $request): RedirectResponse
    {
        $redirect = $this->redirector->route('admin.periods.show', ['period' => $period->id]);

        try {
            $this->dispatcher->dispatch(new UserLogEvent(
                $request->user(),
                UserLog::SUBMIT_EVENT,
                'Period submit'
            ));

            $this->periodService->submit($period);

            return $redirect->with(WithEnums::MESSAGE_WITH, 'Period will be close!');
        } catch (PeriodSubmittedException $exception) {
            return $redirect->with(WithEnums::ERROR_WITH, $exception->getMessage());
        }
    }

    /**
     * @param Period $period
     * @param Request $request
     * @param DatabaseManager $databaseManager
     * @return RedirectResponse
     * @throws Throwable
     */
    public function reset(Period $period, Request $request, DatabaseManager $databaseManager): RedirectResponse
    {
        $redirect = $this->redirector->route('admin.periods.show', ['period' => $period->id]);

        try {
            $this->dispatcher->dispatch(new UserLogEvent(
                $request->user(),
                UserLog::RESET_EVENT,
                'Period reset'
            ));

            $databaseManager->transaction(function () use ($period): void {
                $this->periodService->reset($period);
            });

            return $redirect->with(WithEnums::MESSAGE_WITH, 'Period reset!');
        } catch (PeriodResetException $exception) {
            return $redirect->with(WithEnums::ERROR_WITH, $exception->getMessage());
        }
    }

    /**
     * @param ExportService $exportService
     * @param Request $request
     * @return RedirectResponse
     */
    public function exportCurrentProperties(ExportService $exportService, Request $request): RedirectResponse
    {
        $exportService->export(CurrentPropertiesExcel::class, $request->user()->id, []);

        return $this
            ->redirector
            ->route('admin.export.index')
            ->with('message', 'The export will be process');
    }

    /**
     * @param Period $period
     * @param ExportService $exportService
     * @param Request $request
     * @return RedirectResponse
     */
    public function exportTempTransactions(Period $period, ExportService $exportService, Request $request): RedirectResponse
    {
        $filter = (array) $request->query->get('filter', []);
        $filter[ExportExcelInterface::ID_PARAM] = $period->id;

        $exportService->export(TempTransactionsExcel::class, $request->user()->id, $filter);

        return $this
            ->redirector
            ->route('admin.export.index')
            ->with('message', 'The export will be process');
    }
}
