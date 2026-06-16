<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;

final class SwaggerUiController extends AbstractController
{
    #[Route('/open-api/docs', name: 'swagger_ui', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('swagger/index.html.twig');
    }

    #[Route('/open-api/docs.json', name: 'swagger_ui_spec', methods: [Request::METHOD_GET])]
    public function spec(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): JsonResponse {
        $specification = Yaml::parseFile($projectDir . '/config/openapi.yaml');

        return new JsonResponse($specification);
    }
}
