<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Certificate;
use App\Entity\Enrollment;
use App\Entity\Quiz;
use App\Entity\User;
use App\Enum\EnrollmentStatus;
use App\Exceptions\CertificateException;
use App\Repository\CertificateRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\QuizRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;

class CertificateService
{
    private const string CERTIFICATE_TEMPLATE = 'certificate/certificate.html.twig';
    private const string EMAIL_TEMPLATE = 'email/certificate_ready.html.twig';

    public function __construct(
        private readonly CertificateRepository $certificateRepository,
        private readonly QuizRepository $quizRepository,
        private readonly QuizAttemptRepository $quizAttemptRepository,
        private readonly PdfHelper $pdfHelper,
        private readonly NotificationService $notificationService,
        #[Autowire('%app.base_url%')] private readonly string $baseUrl,
    ) {
    }

    /**
     * Issues a certificate for a completed enrollment when the course offers one.
     * Idempotent: returns the existing certificate if already issued, and null
     * when the course does not (yet) qualify — content not finished, or a
     * quiz-gated course whose quiz has not been passed. Safe to call from both
     * the content-completion and quiz-pass triggers, in either order.
     */
    public function issueForCompletedEnrollment(Enrollment $enrollment): ?Certificate
    {
        $course = $enrollment->getCourse();

        if (!$course->isCertificateEnabled()) {
            return null;
        }

        if ($enrollment->getStatus() !== EnrollmentStatus::Completed) {
            return null;
        }

        if ($course->isRequiresQuiz()) {
            $quiz = $this->quizRepository->findOneByCourse($course);

            if (!$quiz instanceof Quiz || !$this->quizAttemptRepository->hasPassingAttempt($enrollment->getStudent(), $quiz)) {
                return null;
            }
        }

        $existing = $this->certificateRepository->findOneByEnrollment($enrollment);
        if ($existing instanceof Certificate) {
            return $existing;
        }

        $student = $enrollment->getStudent();

        $certificate = new Certificate();
        $certificate->setCredentialId(bin2hex(random_bytes(16)));
        $certificate->setStudent($student);
        $certificate->setCourse($course);
        $certificate->setEnrollment($enrollment);
        $certificate->setStudentName($student->getProfile()->getDisplayName());
        $certificate->setCourseTitle($course->getTitle());
        $certificate->setInstructorName($course->getOwner()->getProfile()->getDisplayName());
        $this->certificateRepository->save($certificate, true);

        $this->notificationService->createEmailNotification(
            [$student->getEmail()],
            'Your Africademy certificate is ready',
            self::EMAIL_TEMPLATE,
            [
                'first_name' => $student->getProfile()->getFirstName(),
                'course_title' => $certificate->getCourseTitle(),
                'credential_id' => $certificate->getCredentialId(),
            ],
        );

        return $certificate;
    }

    /**
     * @throws CertificateException
     */
    public function getStudentCertificate(User $student, Ulid $publicId): Certificate
    {
        $certificate = $this->certificateRepository->findOneByPublicIdAndStudent($publicId, $student);

        if (is_null($certificate)) {
            throw CertificateException::notFound();
        }

        return $certificate;
    }

    /**
     * @throws CertificateException
     */
    public function verify(string $credentialId): Certificate
    {
        $certificate = $this->certificateRepository->findOneByCredentialId($credentialId);

        if (is_null($certificate)) {
            throw CertificateException::notFound();
        }

        return $certificate;
    }

    public function downloadResponse(Certificate $certificate): Response
    {
        $pdf = $this->pdfHelper->landscapeFromTemplate(self::CERTIFICATE_TEMPLATE, [
            'student_name' => $certificate->getStudentName(),
            'course_title' => $certificate->getCourseTitle(),
            'instructor_name' => $certificate->getInstructorName(),
            'credential_id' => $certificate->getCredentialId(),
            'issued_at' => $certificate->getCreatedAt(),
            'verify_url' => $this->verifyUrl($certificate->getCredentialId()),
        ]);

        return $this->pdfHelper->inlineResponse($pdf, $certificate->getCourse()->getSlug() . '-certificate');
    }

    public function createStudentCertificatesQueryBuilder(User $student): QueryBuilder
    {
        return $this->certificateRepository->createStudentCertificatesQueryBuilder($student);
    }

    private function verifyUrl(string $credentialId): string
    {
        return sprintf('%s/api/v1/certificates/verify/%s', rtrim($this->baseUrl, '/'), $credentialId);
    }
}
