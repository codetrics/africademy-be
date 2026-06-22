<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Choice;
use App\Entity\Course;
use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Enum\EnrollmentStatus;
use App\Exceptions\QuizException;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\QuizRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Symfony\Component\Uid\Ulid;

class QuizService
{
    public function __construct(
        private readonly QuizRepository $quizRepository,
        private readonly QuizAttemptRepository $quizAttemptRepository,
        private readonly CourseRepository $courseRepository,
        private readonly EnrollmentRepository $enrollmentRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly CertificateService $certificateService,
        private readonly AccessService $accessService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Replaces the course's quiz with the supplied definition in one transaction.
     * Prior attempts are invalidated so an old passing attempt can't satisfy the
     * certificate gate against the new questions.
     *
     * @param array<string, mixed> $payload
     *
     * @throws QuizException
     */
    public function createOrReplace(Course $course, array $payload): Quiz
    {
        $this->assertDefinition($payload);

        $existingQuiz = $this->quizRepository->findOneByCourse($course);
        $quiz = $existingQuiz ?? new Quiz()->setCourse($course);
        $quiz->setTitle((string) $payload['title']);

        if (array_key_exists('pass_threshold_percent', $payload)) {
            $quiz->setPassThresholdPercent((int) $payload['pass_threshold_percent']);
        }

        // orphanRemoval deletes the previous questions (and their choices) on flush.
        $quiz->getQuestions()->clear();

        foreach (array_values($payload['questions']) as $questionPosition => $questionData) {
            $question = new Question();
            $question->setText((string) $questionData['text']);
            $question->setPosition($questionPosition);

            foreach (array_values($questionData['choices']) as $choicePosition => $choiceData) {
                $choice = new Choice();
                $choice->setText((string) $choiceData['text']);
                $choice->setIsCorrect((bool) ($choiceData['is_correct'] ?? false));
                $choice->setPosition($choicePosition);
                $question->addChoice($choice);
            }

            $quiz->addQuestion($question);
        }

        $this->entityManager->beginTransaction();
        try {
            // Drop prior attempts for the quiz being replaced (no bulk DQL).
            if ($existingQuiz instanceof Quiz) {
                foreach ($this->quizAttemptRepository->findByQuiz($existingQuiz) as $attempt) {
                    $this->quizAttemptRepository->remove($attempt);
                }
            }

            $this->quizRepository->save($quiz, true);
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $quiz;
    }

    /**
     * The student take-view of a course's quiz. Requires the student to be
     * enrolled and to still have access to the course.
     *
     * @throws QuizException
     */
    public function getForCourse(User $student, Ulid $coursePublicId): Quiz
    {
        $course = $this->resolveCourse($coursePublicId);

        $enrollment = $this->enrollmentRepository->findOneByStudentAndCourse($student, $course);
        if (is_null($enrollment)) {
            throw QuizException::notEnrolled();
        }

        if (!$this->accessService->hasAccess($student, $course)) {
            throw QuizException::accessRequired();
        }

        return $this->resolveQuiz($course);
    }

    /**
     * Grades a submission, records the attempt and — on a pass — attempts to
     * issue the course certificate (when content is also complete).
     *
     * @param array<string, string> $answers map of question public id to chosen choice public id
     *
     * @throws QuizException
     */
    public function submit(User $student, Ulid $coursePublicId, array $answers): QuizAttempt
    {
        $course = $this->resolveCourse($coursePublicId);
        $quiz = $this->resolveQuiz($course);

        $enrollment = $this->enrollmentRepository->findOneByStudentAndCourse($student, $course);
        if (is_null($enrollment)) {
            throw QuizException::notEnrolled();
        }

        if (!$this->accessService->hasAccess($student, $course)) {
            throw QuizException::accessRequired();
        }

        $questions = $quiz->getQuestions();
        $totalQuestions = $questions->count();
        if ($totalQuestions === 0) {
            throw QuizException::invalidSubmission('This quiz has no questions.');
        }

        $correctCount = 0;
        $recordedAnswers = [];
        foreach ($questions as $question) {
            $questionId = (string) $question->getPublicId();
            $selectedChoiceId = $answers[$questionId] ?? null;

            if (!is_null($selectedChoiceId)) {
                $recordedAnswers[$questionId] = (string) $selectedChoiceId;
            }

            foreach ($question->getCorrectChoices() as $correctChoice) {
                if ((string) $correctChoice->getPublicId() === $selectedChoiceId) {
                    ++$correctCount;
                    break;
                }
            }
        }

        $scorePercent = (int) round($correctCount / $totalQuestions * 100);
        $passed = $scorePercent >= $quiz->getPassThresholdPercent();

        $attempt = new QuizAttempt();
        $attempt->setQuiz($quiz);
        $attempt->setStudent($student);
        $attempt->setScorePercent($scorePercent);
        $attempt->setPassed($passed);
        $attempt->setAnswers($recordedAnswers);
        $this->quizAttemptRepository->save($attempt, true);

        if ($passed) {
            // A quiz-only course (no published lessons) is completed by passing
            // the quiz, so the certificate gate (status = Completed) is satisfied.
            if ($enrollment->getStatus() !== EnrollmentStatus::Completed
                && count($this->lessonRepository->findPublishedByCourseOrdered($course)) === 0
            ) {
                $enrollment->setStatus(EnrollmentStatus::Completed);
                $enrollment->setCompletedAt(new DateTime());
                $this->enrollmentRepository->save($enrollment, true);
            }

            $this->certificateService->issueForCompletedEnrollment($enrollment);
        }

        return $attempt;
    }

    /**
     * @throws QuizException
     */
    public function studentAttemptsQueryBuilder(User $student, Ulid $coursePublicId): QueryBuilder
    {
        $quiz = $this->resolveQuiz($this->resolveCourse($coursePublicId));

        return $this->quizAttemptRepository->createStudentAttemptsQueryBuilder($student, $quiz);
    }

    /**
     * @throws QuizException
     */
    private function resolveCourse(Ulid $coursePublicId): Course
    {
        $course = $this->courseRepository->findOneByPublicId($coursePublicId);

        if (is_null($course)) {
            throw QuizException::courseNotFound();
        }

        return $course;
    }

    /**
     * @throws QuizException
     */
    private function resolveQuiz(Course $course): Quiz
    {
        $quiz = $this->quizRepository->findOneByCourse($course);

        if (is_null($quiz)) {
            throw QuizException::quizNotFound();
        }

        return $quiz;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws QuizException
     */
    private function assertDefinition(array $payload): void
    {
        if (!array_key_exists('title', $payload) || trim((string) $payload['title']) === '') {
            throw QuizException::invalidDefinition('A quiz title is required.');
        }

        if (!array_key_exists('questions', $payload) || !is_array($payload['questions']) || $payload['questions'] === []) {
            throw QuizException::invalidDefinition('A quiz must have at least one question.');
        }

        foreach ($payload['questions'] as $questionData) {
            if (!is_array($questionData) || trim((string) ($questionData['text'] ?? '')) === '') {
                throw QuizException::invalidDefinition('Every question must have text.');
            }

            $choices = $questionData['choices'] ?? null;
            if (!is_array($choices) || count($choices) < 2) {
                throw QuizException::invalidDefinition('Every question must have at least two choices.');
            }

            $correctCount = 0;
            foreach ($choices as $choiceData) {
                if (!is_array($choiceData) || trim((string) ($choiceData['text'] ?? '')) === '') {
                    throw QuizException::invalidDefinition('Every choice must have text.');
                }

                if (($choiceData['is_correct'] ?? false) === true) {
                    ++$correctCount;
                }
            }

            if ($correctCount !== 1) {
                throw QuizException::invalidDefinition('Every question must have exactly one correct choice.');
            }
        }
    }
}
