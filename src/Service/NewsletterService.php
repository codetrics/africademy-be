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
     * Starts a double opt-in: the subscription is created (or re-armed) as
     * Pending and a confirmation email is queued. It only becomes Confirmed once
     * the user clicks the emailed link (see confirm()).
     *
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

            $existing->setStatus(NewsletterStatus::Pending);
            $existing->setConfirmationToken(bin2hex(random_bytes(16)));
            $this->newsletterSubscriptionRepository->save($existing, true);
            $this->queueConfirmationEmail($existing);

            return $existing;
        }

        $subscription = new NewsletterSubscription();
        $subscription->setEmail($email);
        $this->newsletterSubscriptionRepository->save($subscription, true);
        $this->queueConfirmationEmail($subscription);

        return $subscription;
    }

    /**
     * Confirms a pending subscription via its emailed token. Idempotent for an
     * already-confirmed subscription.
     *
     * @throws NewsletterException
     */
    public function confirm(string $token): NewsletterSubscription
    {
        $subscription = $this->newsletterSubscriptionRepository->findOneByConfirmationToken($token);

        if (!$subscription instanceof NewsletterSubscription) {
            throw NewsletterException::subscriptionNotFound();
        }

        $subscription->setStatus(NewsletterStatus::Confirmed);
        $subscription->setConfirmationToken(null);
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

    private function queueConfirmationEmail(NewsletterSubscription $subscription): void
    {
        $confirmUrl = $this->urlGenerator->generate(
            'api_newsletter_confirm',
            ['version' => 'v1', 'token' => (string) $subscription->getConfirmationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->notificationService->createEmailNotification(
            [$subscription->getEmail()],
            'Confirm your Africademy newsletter subscription',
            self::WELCOME_TEMPLATE,
            [
                'heading' => 'Confirm your subscription',
                'body' => 'Please confirm you would like to receive the Africademy newsletter.',
                'action_url' => $confirmUrl,
                'action_label' => 'Confirm subscription',
            ],
        );
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
