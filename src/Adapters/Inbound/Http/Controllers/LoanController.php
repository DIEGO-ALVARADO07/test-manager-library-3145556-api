<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Ports\Clock;
use Library\Application\Services\GetLoanService;
use Library\Application\Services\LendBooksService;
use Library\Application\Services\ListLoansService;
use Library\Application\Services\OverdueService;
use Library\Application\Services\ReturnItemService;
use Library\Domain\Loan;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\LoanStatus;

final class LoanController
{
    public function store(Request $request, LendBooksService $service, Clock $clock): JsonResponse
    {
        $data = Input::validate($request->json()->all(), [
            'member_id' => 'required|uuid',
            'items' => 'required|array|min:1|max:3',
            'items.*.book_id' => 'required|uuid',
        ]);

        $bookIds = array_map(static fn (array $item): string => $item['book_id'], array_values($data['items']));
        $loan = $service->lend($data['member_id'], $bookIds, (string) $request->attributes->get('auth_user_id'));

        return new JsonResponse(Presenter::loan($loan, $clock->now()), 201);
    }

    public function index(Request $request, ListLoansService $service, Clock $clock): JsonResponse
    {
        [$page, $size] = Input::pagination($request);
        $filters = Input::validate($request->query(), [
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|in:open,overdue,closed',
            'member_id' => 'nullable|uuid',
        ]);

        $range = null;
        if (isset($filters['from']) || isset($filters['to'])) {
            $range = DateRange::fromStrings($filters['from'] ?? '1970-01-01', $filters['to'] ?? '9999-12-31');
        }
        $status = isset($filters['status']) ? LoanStatus::from($filters['status']) : null;

        $result = $service->list($range, $status, $filters['member_id'] ?? null, $page, $size);
        $now = $clock->now();

        return new JsonResponse(Presenter::page($result, static fn (Loan $loan): array => Presenter::loan($loan, $now)));
    }

    public function show(string $id, GetLoanService $service, Clock $clock): JsonResponse
    {
        return new JsonResponse(Presenter::loan($service->get($id), $clock->now()));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return new JsonResponse(Presenter::item($service->return($loanId, $itemId)));
    }

    public function overdue(Request $request, OverdueService $service): JsonResponse
    {
        [$page, $size] = Input::pagination($request);
        $result = $service->list($page, $size);
        $now = $result['now'];

        return new JsonResponse(Presenter::page($result['page'], static fn (array $row): array => [
            'loan_id' => $row['loan_id'],
            'item_id' => $row['item_id'],
            'book_title' => $row['book_title'],
            'member_name' => $row['member_name'],
            'due_at' => Presenter::date($row['due_at']),
            'days_late' => OverdueService::daysLate($row['due_at'], $now),
        ]));
    }
}
