<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {
    }

    /**
     * Renders the Twig template to HTML and sends the email.
     *
     * @param string[] $to
     * @param string[] $cc
     * @param array<string, mixed> $context
     */
    public function sendMail(
        string $subject,
        array $to,
        array $cc,
        string $from,
        string $htmlTemplate,
        array $context,
        ?string $attachmentPath = null,
    ): void {
        $email = new Email();
        $email->from($from);
        $email->subject($subject);
        $email->html($this->twig->render($htmlTemplate, $context));

        foreach ($to as $address) {
            $email->addTo($address);
        }

        foreach ($cc as $address) {
            $email->addCc($address);
        }

        if (!is_null($attachmentPath)) {
            $email->attachFromPath($attachmentPath);
        }

        $this->mailer->send($email);
    }
}
