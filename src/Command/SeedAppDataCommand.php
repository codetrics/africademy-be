<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\Money;
use App\Entity\Quiz;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Enum\CourseLevel;
use App\Enum\CourseStatus;
use App\Enum\LessonStatus;
use App\Enum\LessonType;
use App\Enum\SubscriptionInterval;
use App\Enum\UserStatus;
use App\Repository\CategoryRepository;
use App\Repository\CourseRepository;
use App\Repository\QuizRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\UserRepository;
use App\Service\OtpLoginService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:data:seed',
    description: 'Seed reference data (categories, subscription plan) plus the launch facilitator and course (idempotent).',
)]
final class SeedAppDataCommand extends AbstractCommand
{
    private const array DEFAULT_CATEGORIES = ['Wealth', 'Business', 'Brand', 'Transgenerational Wealth'];

    private const string PLAN_SLUG = 'premium-monthly';
    private const string PLAN_NAME = 'Premium';
    private const int PLAN_PRICE_CENTS = 15000;

    private const string FACILITATOR_EMAIL = 'calvin.facilitator@codestudio.co.za';
    private const string FACILITATOR_FIRST_NAME = 'Dr. Nontokozo';
    private const string FACILITATOR_LAST_NAME = 'Mangquku';

    /** Days from seeding during which the facilitator skips OTP on login. */
    private const int OTP_EXEMPTION_DAYS = 7;

    private const string COURSE_CATEGORY_SLUG = 'transgenerational-wealth';

    /**
     * @var array<string, mixed>
     */
    private const array COURSE = [
        'slug' => 'build-7-8-income-streams',
        'title' => 'Build 7 – 8 Income Streams',
        'tagline' => 'Build 7–8 income streams & secure your financial future',
        'description' => "This transformational 8-week course is designed to break you out of financial limitation and strategically guide you from one income stream to seven, even eight, using proven biblical principles and practical execution. You will learn how to identify hidden opportunities, activate what's already in your hands, build sustainable systems and structures, and multiply income with clarity, discipline, and purpose.",
        'status' => 'published',
        'level' => 'beginner',
        'price_cents' => 450000,
        'currency' => 'ZAR',
        'is_free' => false,
        'is_purchasable' => true,
        'included_in_subscription' => false,
        'certificate_enabled' => true,
        'requires_quiz' => true,
        'rating_average' => 5.0,
        'rating_count' => 318,
        'enrollment_count' => 2140,
        'tags' => ['Wealth', 'Income Streams', 'Biblical Principles', 'Live', 'Online'],
        'objectives' => [
            'Identify income opportunities aligned to your skills & calling',
            'Build the framework for 2–3 new income streams',
            'Master biblical systems for stewardship & multiplication',
            'Develop a personalised income expansion plan',
        ],
    ];

    /**
     * @var list<array{position: int, title: string, type: string, status: string, duration_minutes: int}>
     */
    private const array LESSONS = [
        ['position' => 1, 'title' => 'Breaking Financial Limitation', 'type' => 'video', 'status' => 'published', 'duration_minutes' => 32],
        ['position' => 2, 'title' => 'Identifying Hidden Opportunities', 'type' => 'video', 'status' => 'published', 'duration_minutes' => 29],
        ['position' => 3, 'title' => "Activating What's In Your Hands", 'type' => 'video', 'status' => 'published', 'duration_minutes' => 36],
        ['position' => 4, 'title' => 'Biblical Systems for Multiplication', 'type' => 'video', 'status' => 'published', 'duration_minutes' => 41],
        ['position' => 5, 'title' => 'Building Sustainable Structures', 'type' => 'video', 'status' => 'published', 'duration_minutes' => 38],
        ['position' => 6, 'title' => 'Risk-Managed Wealth Beyond Income', 'type' => 'video', 'status' => 'published', 'duration_minutes' => 35],
        ['position' => 7, 'title' => 'Your Income Expansion Plan', 'type' => 'video', 'status' => 'published', 'duration_minutes' => 44],
        ['position' => 8, 'title' => 'Trans-Generational Legacy', 'type' => 'video', 'status' => 'published', 'duration_minutes' => 60],
    ];

    private const string QUIZ_TITLE = 'Build 7–8 Income Streams — Final Assessment';
    private const int QUIZ_PASS_THRESHOLD_PERCENT = 70;

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly UserRepository $userRepository,
        private readonly CourseRepository $courseRepository,
        private readonly QuizRepository $quizRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'password',
            null,
            InputOption::VALUE_REQUIRED,
            "Plaintext password for the seeded facilitator (required only when the facilitator does not yet exist)."
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $password = (string) ($input->getOption('password') ?? '');
        $facilitator = $this->userRepository->findOneByEmail(self::FACILITATOR_EMAIL);

        if (!$facilitator instanceof User && $password === '') {
            $io->error('The --password option is required to seed the facilitator.');

            return self::FAILURE;
        }

        $this->getLogger()->info('Starting application data seed');

        try {
            $this->entityManager->beginTransaction();

            $this->seedCategories($io);
            $this->seedSubscriptionPlan($io);

            if (!$facilitator instanceof User) {
                $facilitator = $this->seedFacilitator($password);
                $io->success(sprintf('Seeded facilitator %s.', self::FACILITATOR_EMAIL));
            } else {
                $io->writeln(sprintf('Facilitator %s already present.', self::FACILITATOR_EMAIL));
            }

            $this->seedCourse($facilitator, $io);

            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            $this->getLogger()->error(sprintf('Application data seed failed: %s', $exception->getMessage()));
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->getLogger()->info('Application data seed completed');
        $io->success('Seeding complete.');

        return self::SUCCESS;
    }

    private function seedCategories(SymfonyStyle $io): void
    {
        $created = 0;

        foreach (self::DEFAULT_CATEGORIES as $name) {
            $slug = $this->slugger->slug($name)->lower()->toString();

            if (!is_null($this->categoryRepository->findOneBySlug($slug))) {
                continue;
            }

            $category = new Category();
            $category->setName($name);
            $category->setSlug($slug);
            $this->categoryRepository->save($category, true);
            $created++;
        }

        $this->getLogger()->info(sprintf('Seeded %d new categories', $created));
        $io->writeln(sprintf('Categories: %d created, %d already present.', $created, count(self::DEFAULT_CATEGORIES) - $created));
    }

    private function seedSubscriptionPlan(SymfonyStyle $io): void
    {
        if (!is_null($this->subscriptionPlanRepository->findOneBySlug(self::PLAN_SLUG))) {
            $io->writeln('Subscription plan already present.');

            return;
        }

        $plan = new SubscriptionPlan();
        $plan->setName(self::PLAN_NAME);
        $plan->setSlug(self::PLAN_SLUG);
        $plan->setInterval(SubscriptionInterval::Monthly);
        $plan->setPrice(new Money(self::PLAN_PRICE_CENTS));
        $this->subscriptionPlanRepository->save($plan, true);

        $this->getLogger()->info('Seeded subscription plan ' . self::PLAN_SLUG);
        $io->writeln('Seeded the Premium monthly subscription plan.');
    }

    private function seedFacilitator(string $password): User
    {
        $profile = new UserProfile();
        $profile->setFirstName(self::FACILITATOR_FIRST_NAME);
        $profile->setLastName(self::FACILITATOR_LAST_NAME);

        $facilitator = new User();
        $facilitator->setEmail(self::FACILITATOR_EMAIL);
        $facilitator->setProfile($profile);
        $facilitator->setRoles([User::ROLE_FACILITATOR]);
        $facilitator->setStatus(UserStatus::Active);
        $facilitator->setEmailVerifiedAt(new DateTime());
        $facilitator->setPassword($this->passwordHasher->hashPassword($facilitator, $password));

        // Skip OTP on login until OTP_EXEMPTION_DAYS from now: the trust window ends
        // OTP_TRUST_TTL_SECONDS after lastOtpAt, so place lastOtpAt that far ahead.
        $lastOtpAt = new DateTime(sprintf('+%d days', self::OTP_EXEMPTION_DAYS));
        $lastOtpAt->modify(sprintf('-%d seconds', OtpLoginService::OTP_TRUST_TTL_SECONDS));
        $facilitator->setLastOtpAt($lastOtpAt);

        $this->userRepository->save($facilitator, true);

        $this->getLogger()->info('Seeded facilitator ' . self::FACILITATOR_EMAIL);

        return $facilitator;
    }

    private function seedCourse(User $owner, SymfonyStyle $io): void
    {
        if ($this->courseRepository->findPublishedBySlug(self::COURSE['slug']) instanceof Course) {
            $io->writeln(sprintf('Course %s already present.', self::COURSE['slug']));

            return;
        }

        $category = $this->categoryRepository->findOneBySlug(self::COURSE_CATEGORY_SLUG);

        if (!$category instanceof Category) {
            throw new Exception(sprintf('Course category "%s" was not seeded.', self::COURSE_CATEGORY_SLUG));
        }

        $course = new Course();
        $course->setTitle(self::COURSE['title']);
        $course->setSlug(self::COURSE['slug']);
        $course->setTagline(self::COURSE['tagline']);
        $course->setDescription(self::COURSE['description']);
        $course->setStatus(CourseStatus::from(self::COURSE['status']));
        $course->setLevel(CourseLevel::from(self::COURSE['level']));
        $course->setCategory($category);
        $course->setOwner($owner);
        $course->setPrice(new Money(self::COURSE['price_cents'], self::COURSE['currency']));
        $course->setIsFree(self::COURSE['is_free']);
        $course->setIsPurchasable(self::COURSE['is_purchasable']);
        $course->setIncludedInSubscription(self::COURSE['included_in_subscription']);
        $course->setCertificateEnabled(self::COURSE['certificate_enabled']);
        $course->setRequiresQuiz(self::COURSE['requires_quiz']);
        $course->setRatingAverage(self::COURSE['rating_average']);
        $course->setRatingCount(self::COURSE['rating_count']);
        $course->adjustEnrollmentCount(self::COURSE['enrollment_count']);
        $course->setTags(self::COURSE['tags']);
        $course->setObjectives(self::COURSE['objectives']);

        foreach (self::LESSONS as $lessonData) {
            $lesson = new Lesson();
            $lesson->setTitle($lessonData['title']);
            $lesson->setType(LessonType::from($lessonData['type']));
            $lesson->setStatus(LessonStatus::from($lessonData['status']));
            $lesson->setPosition($lessonData['position']);
            $lesson->setDurationMinutes($lessonData['duration_minutes']);
            $course->addLesson($lesson);
        }

        // Cascades persist to the lessons attached above.
        $this->courseRepository->save($course, true);

        $quiz = new Quiz();
        $quiz->setCourse($course);
        $quiz->setTitle(self::QUIZ_TITLE);
        $quiz->setPassThresholdPercent(self::QUIZ_PASS_THRESHOLD_PERCENT);
        $this->quizRepository->save($quiz, true);

        $this->getLogger()->info(sprintf('Seeded course %s with %d lessons and a quiz', self::COURSE['slug'], count(self::LESSONS)));
        $io->writeln(sprintf('Seeded course %s (%d lessons + quiz).', self::COURSE['slug'], count(self::LESSONS)));
    }
}
