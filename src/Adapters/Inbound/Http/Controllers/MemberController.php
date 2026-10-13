<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Services\CreateMemberService;
use Library\Application\Services\GetMemberService;
use Library\Application\Services\SearchMembersService;

final class MemberController
{
    public function index(Request $request, SearchMembersService $service): JsonResponse
    {
        [$page, $size] = Input::pagination($request);
        $filters = Input::validate($request->query(), ['q' => 'nullable|string|max:200']);

        return new JsonResponse(Presenter::page($service->search($filters['q'] ?? null, $page, $size), Presenter::member(...)));
    }

    public function show(string $id, GetMemberService $service): JsonResponse
    {
        $result = $service->get($id);

        return new JsonResponse(Presenter::member($result['member']) + ['active_loans' => $result['active_loans']]);
    }

    public function store(Request $request, CreateMemberService $service): JsonResponse
    {
        $data = Input::validate($request->json()->all(), [
            'full_name' => 'required|string|max:200',
            'document_number' => 'required|string|max:30',
        ]);

        return new JsonResponse(Presenter::member($service->create($data['full_name'], $data['document_number'])), 201);
    }
}
