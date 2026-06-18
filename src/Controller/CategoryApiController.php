<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CategoryApiController extends AbstractController
{
    #[Route(
        '/api/{version}/categories',
        name: 'api_category_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        CategoryRepository $categoryRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $categories = $categoryRepository->findBy([], ['name' => 'ASC']);

        $response = new JsonResponse();
        $response->setData(['categories' => json_decode($serializerService->serialize($categories))]);

        return $response;
    }
}
