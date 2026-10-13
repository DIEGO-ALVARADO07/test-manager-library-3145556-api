<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Services\CreateLibrarianService;
use Library\Application\Services\LoginService;

final class AuthController
{
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function login(Request $request, LoginService $service): JsonResponse
    {
        $data = Input::validate($request->json()->all(), [
            'username' => 'required|string|max:120',
            'password' => 'required|string|max:200',
        ]);

        $result = $service->login($data['username'], $data['password']);

        return new JsonResponse([
            'access_token' => $result['token']->token,
            'token_type' => 'Bearer',
            'expires_in' => $result['token']->expiresIn,
            'role' => $result['role']->value,
        ]);
    }

    public function createUser(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $data = Input::validate($request->json()->all(), [
            'username' => 'required|string|max:120',
            'password' => 'required|string|max:200',
            'role' => 'required|string',
        ]);

        $user = $service->create($data['username'], $data['password'], $data['role']);

        return new JsonResponse(Presenter::user($user), 201);
    }
}
