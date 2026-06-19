<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsletterSubscription;
use App\Enum\NewsletterStatus;
use App\Exceptions\NewsletterException;
use App\Repository\NewsletterSubscriptionRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NewsletterService
{
    private const string WELCOME_TEMPLATE = 'email/generic.html.twig';

    public function __construct(
        private readonly NewsletterSubscriptionRepository $newsletterSubscriptionRepository,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @throws NewsletterException
     */
    public function subscribe(string $email): NewsletterSubscription
    {
        $email = strtolower(trim($email));
        $existing = $this->newsletterSubscriptionRepository->findOneByEmail($email);

        if ($existing instanceof NewsletterSubscription) {
            if ($existing->getStatus() === NewsletterStatus::Confirmed) {
                throw NewsletterException::alreadySubscribed();
            }

            $existing->setStatus(NewsletterStatus::Confirmed);
            $this->newsletterSubscriptionRepository->save($existing, true);
            $this->queueWelcomeEmail($existing);

            return $existing;
        }

        $subscription = new NewsletterSubscription();
        $subscription->setEmail($email);
        $this->newsletterSubscriptionRepository->save($subscription, true);
        $this->queueWelcomeEmail($subscription);

        return $subscription;
    }

    /**
     * @throws NewsletterException
     */
    public function unsubscribe(string $token): NewsletterSubscription
    {
        $subscription = $this->newsletterSubscriptionRepository->findOneByUnsubscribeToken($token);

        if (!$subscription instanceof NewsletterSubscription) {
            throw NewsletterException::subscriptionNotFound();
        }

        $subscription->setStatus(NewsletterStatus::Unsubscribed);
        $this->newsletterSubscriptionRepository->save($subscription, true);

        return $subscription;
    }

    private function queueWelcomeEmail(NewsletterSubscription $subscription): void
    {
        $unsubscribeUrl = $this->urlGenerator->generate(
            'api_newsletter_unsubscribe',
            ['version' => 'v1', 'token' => $subscription->getUnsubscribeToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->notificationService->createEmailNotification(
            [$subscription->getEmail()],
            'Welcome to the Africademy newsletter',
            self::WELCOME_TEMPLATE,
            [
                'heading' => 'You are subscribed',
                'body' => 'Thanks for subscribing to the Africademy newsletter. You will receive stories, guides, and insights for African builders. You can unsubscribe at any time.',
                'action_url' => $unsubscribeUrl,
                'action_label' => 'Unsubscribe',
            ],
        );
    }
}
