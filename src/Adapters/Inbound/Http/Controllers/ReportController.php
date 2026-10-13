<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Application\Services\MostBorrowedReportService;
use Library\Domain\ValueObjects\DateRange;

final class ReportController
{
    public function mostBorrowed(Request $request, MostBorrowedReportService $service): JsonResponse
    {
        $data = Input::validate($request->query(), [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d',
        ]);

        $range = DateRange::fromStrings($data['from'], $data['to']);

        return new JsonResponse([
            'from' => $data['from'],
            'to' => $data['to'],
            'rows' => $service->report($range),
        ]);
    }
}
