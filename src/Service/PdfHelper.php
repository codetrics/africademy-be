<?php

declare(strict_types=1);

namespace App\Service;

use Spatie\Browsershot\Browsershot;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PdfHelper
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%app.browsershot.node_binary%')] private readonly string $nodeBinary,
        #[Autowire('%app.browsershot.npm_binary%')] private readonly string $npmBinary,
        #[Autowire('%app.browsershot.chrome_path%')] private readonly string $chromePath,
    ) {
    }

    /**
     * Render a Twig template to a landscape A4 PDF and return the raw bytes.
     *
     * @param array<string, mixed> $context
     */
    public function landscapeFromTemplate(string $template, array $context = []): string
    {
        return $this->renderLandscape($this->twig->render($template, $context));
    }

    public function inlineResponse(string $pdf, string $filename): Response
    {
        return new Response(
            $pdf,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s.pdf"', $filename),
            ],
        );
    }

    private function renderLandscape(string $html): string
    {
        $browsershot = Browsershot::html($html)
            ->setNodeBinary($this->nodeBinary)
            ->setNpmBinary($this->npmBinary)
            ->format('A4')
            ->landscape()
            ->showBackground()
            ->noSandbox()
            ->addChromiumArguments([
                'disable-setuid-sandbox',
                'disable-dev-shm-usage',
                'single-process',
            ])
            ->timeout(15);

        if ($this->chromePath !== '') {
            $browsershot->setChromePath($this->chromePath);
        }

        return $browsershot->pdf();
    }
}
