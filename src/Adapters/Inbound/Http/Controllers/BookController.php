<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Library\Adapters\Inbound\Http\Input;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Services\CreateBookService;
use Library\Application\Services\DeleteBookService;
use Library\Application\Services\GetBookService;
use Library\Application\Services\GetCoverService;
use Library\Application\Services\ListCategoriesService;
use Library\Application\Services\RemoveCoverService;
use Library\Application\Services\SearchBooksService;
use Library\Application\Services\UpdateBookService;
use Library\Application\Services\UploadCoverService;
use Library\Domain\Book;
use Library\Domain\Category;
use Library\Domain\Exceptions\InvalidValue;

final class BookController
{
    public function categories(ListCategoriesService $service): JsonResponse
    {
        $items = array_map(static fn (Category $c): array => ['id' => $c->id, 'name' => $c->name], $service->list());

        return new JsonResponse(['items' => $items, 'page' => 1, 'size' => count($items), 'total' => count($items)]);
    }

    public function index(Request $request, SearchBooksService $service): JsonResponse
    {
        [$page, $size] = Input::pagination($request);
        $filters = Input::validate($request->query(), ['q' => 'nullable|string|max:200', 'category_id' => 'nullable|uuid']);

        $result = $service->search($filters['q'] ?? null, $filters['category_id'] ?? null, $page, $size);

        return new JsonResponse(Presenter::page($result, Presenter::book(...)));
    }

    public function show(string $id, GetBookService $service): JsonResponse
    {
        return new JsonResponse(Presenter::book($service->get($id)));
    }

    public function store(Request $request, CreateBookService $service): JsonResponse
    {
        $data = Input::validate($request->json()->all(), [
            'title' => 'required|string|max:250',
            'author' => 'required|string|max:200',
            'isbn' => 'required|string',
            'category_id' => 'required|uuid',
            'total_copies' => 'required|integer',
        ]);

        $book = $service->create($data['title'], $data['author'], $data['isbn'], $data['category_id'], (int) $data['total_copies']);

        return new JsonResponse(Presenter::book($book), 201);
    }

    public function update(string $id, Request $request, UpdateBookService $service): JsonResponse
    {
        $data = Input::validate($request->json()->all(), [
            'title' => 'required|string|max:250',
            'author' => 'required|string|max:200',
            'category_id' => 'required|uuid',
            'total_copies' => 'required|integer',
        ]);

        $book = $service->update($id, $data['title'], $data['author'], $data['category_id'], (int) $data['total_copies']);

        return new JsonResponse(Presenter::book($book));
    }

    public function destroy(string $id, DeleteBookService $service): Response
    {
        $service->delete($id);

        return new Response('', 204);
    }

    public function uploadCover(string $id, Request $request, UploadCoverService $service): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            throw new InvalidValue('Debe enviar una imagen válida en el campo file (máximo 2 MB).', [
                ['field' => 'file', 'issue' => 'archivo ausente, inválido o mayor a 2 MB'],
            ]);
        }

        $book = $service->upload($id, (string) file_get_contents($file->getRealPath()));

        return new JsonResponse(Presenter::book($book));
    }

    public function removeCover(string $id, RemoveCoverService $service): Response
    {
        $service->remove($id);

        return new Response('', 204);
    }

    public function cover(string $id, GetCoverService $service): Response
    {
        $cover = $service->get($id);

        return new Response($cover['contents'], 200, ['Content-Type' => $cover['mime']]);
    }
}
