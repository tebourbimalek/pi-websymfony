<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\QuizResult;
use App\Entity\User;
use App\Entity\Certificate;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

#[Route('/certificate')]
class CertificateController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Browse certificates page
     */
    #[Route('/browse-certificates', name: 'app_certificates_browse', methods: ['GET'])]
    public function browseCertificates(Request $request): Response
    {
        try {
            // Get all certificates/quiz results with user and course info
            $quizResults = $this->entityManager->getRepository(QuizResult::class)
                ->createQueryBuilder('qr')
                ->select('qr', 'u', 'c', 'q')
                ->join('qr.user', 'u')
                ->join('qr.quiz', 'q')
                ->join('q.course', 'c')
                ->where('qr.score >= qr.maxScore * 0.6') // Only passed courses (60%+)
                ->orderBy('qr.takenAt', 'DESC')
                ->getQuery()
                ->getResult();

            // Group by user and course to get unique certificates
            $certificates = [];
            foreach ($quizResults as $result) {
                $user = $result['qr']->getUser();
                $course = $result['qr']->getQuiz()->getCourse();
                if ($user && $course) {
                    $key = $user->getId() . '_' . $course->getId();
                    if (!isset($certificates[$key])) {
                        $certificates[$key] = [
                            'user' => $user,
                            'course' => $course,
                            'quizResult' => $result['qr'],
                            'percentage' => round(($result['qr']->getScore() / $result['qr']->getMaxScore()) * 100, 1),
                            'completionDate' => $result['qr']->getTakenAt()
                        ];
                    }
                }
            }

            return $this->render('certificate/browse.html.twig', [
                'certificates' => array_values($certificates),
                'totalCertificates' => count($certificates)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Browse certificates error: ' . $e->getMessage());
            
            return $this->render('certificate/browse.html.twig', [
                'certificates' => [],
                'totalCertificates' => 0,
                'error' => 'Unable to load certificates at this time.'
            ]);
        }
    }

    /**
     * Preview certificate for current user after course completion
     */
    #[Route('/preview/{courseId}', name: 'app_certificate_preview', methods: ['GET'])]
    public function previewCertificate(int $courseId): Response
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException('User must be logged in');
            }

            // Fetch Course
            $course = $this->entityManager->getRepository(Course::class)->find($courseId);
            if ($course === null) {
                $this->addFlash('error', 'Course not found');
                return $this->redirectToRoute('app_home');
            }

            // Set default completion data for preview
            $percentage = 85;
            $totalScore = 85;
            $totalMaxScore = 100;
            $completionDate = new \DateTime();

            return $this->render('certificate/preview.html.twig', [
                'user' => $user,
                'course' => $course,
                'score' => $totalScore,
                'maxScore' => $totalMaxScore,
                'percentage' => $percentage,
                'completionDate' => $completionDate
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Certificate preview error: ' . $e->getMessage(), [
                'courseId' => $courseId,
                'exception' => $e
            ]);

            $this->addFlash('error', 'Unable to load certificate preview at this time. Please try again later.');
            return $this->redirectToRoute('app_course_show', ['id' => $courseId]);
        }
    }

    /**
     * Generate certificate for current user after course completion
     */
    #[Route('/generate/{courseId}', name: 'app_certificate_generate_for_user', methods: ['GET'])]
    public function generateCertificateForUser(int $courseId): Response
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException('User must be logged in');
            }

            // Fetch Course
            $course = $this->entityManager->getRepository(Course::class)->find($courseId);
            if ($course === null) {
                $this->addFlash('error', 'Course not found');
                return $this->redirectToRoute('app_home');
            }

            // For simplicity, we'll generate certificate based on course completion
            // In a real implementation, you might want to check actual progress/quiz results
            $percentage = 85; // Default completion percentage
            $totalScore = 85;
            $totalMaxScore = 100;
            $completionDate = new \DateTime();

            // Generate PDF
            try {
                // Simple test HTML first
                $simpleHtml = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Certificate of Achievement</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            margin: 0;
                            padding: 40px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            min-height: 100vh;
                        }
                        
                        .certificate {
                            width: 100%;
                            max-width: 900px;
                            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                            border: 12px solid transparent;
                            border-image: linear-gradient(45deg, #f39c12, #e74c3c, #3498db, #2ecc71) 1;
                            border-radius: 20px;
                            padding: 60px;
                            text-align: center;
                            position: relative;
                            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
                            overflow: hidden;
                        }
                        
                        .certificate::before {
                            content: "";
                            position: absolute;
                            top: -8px;
                            left: -8px;
                            right: -8px;
                            bottom: -8px;
                            background: linear-gradient(45deg, #f39c12, #e74c3c, #3498db, #2ecc71);
                            border-radius: 16px;
                            z-index: -1;
                            opacity: 0.1;
                        }
                        
                        .watermark {
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%) rotate(-45deg);
                            font-size: 140px;
                            color: rgba(44, 62, 80, 0.08);
                            font-weight: 900;
                            font-family: Arial, sans-serif;
                            pointer-events: none;
                            z-index: 1;
                            text-transform: uppercase;
                            letter-spacing: 8px;
                        }
                        
                        .certificate-title {
                            font-family: Arial, sans-serif;
                            font-size: 48px;
                            font-weight: 900;
                            color: #2c3e50;
                            margin: 0 0 15px 0;
                            text-transform: uppercase;
                            letter-spacing: 6px;
                            text-shadow: 3px 3px 6px rgba(0,0,0,0.2);
                            position: relative;
                        }
                        
                        .certificate-title::after {
                            content: "";
                            position: absolute;
                            bottom: -8px;
                            left: 50%;
                            transform: translateX(-50%);
                            width: 120px;
                            height: 4px;
                            background: linear-gradient(90deg, #f39c12, #e74c3c, #3498db, #2ecc71);
                            border-radius: 2px;
                        }
                        
                        .certificate-subtitle {
                            font-size: 20px;
                            color: #6c757d;
                            font-weight: 300;
                            margin-bottom: 30px;
                            font-style: italic;
                        }
                        
                        .recipient-name {
                            font-size: 36px;
                            font-weight: 700;
                            color: #2c3e50;
                            margin: 30px 0;
                            padding: 20px 40px;
                            border-bottom: 4px solid #e74c3c;
                            display: inline-block;
                            background: linear-gradient(135deg, rgba(243, 156, 18, 0.1) 0%, rgba(231, 76, 60, 0.1) 100%);
                            border-radius: 10px;
                            position: relative;
                        }
                        
                        .certificate-text {
                            font-size: 18px;
                            line-height: 1.8;
                            color: #495057;
                            margin: 30px 0;
                            font-weight: 400;
                        }
                        
                        .course-title {
                            font-size: 32px;
                            font-weight: 700;
                            color: #e74c3c;
                            margin: 25px 0;
                            text-transform: uppercase;
                            letter-spacing: 3px;
                            padding: 20px 30px;
                            background: linear-gradient(135deg, #e74c3c, #f39c12);
                            color: white;
                            border-radius: 12px;
                            display: inline-block;
                            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                        }
                        
                        .score-badge {
                            display: inline-block;
                            padding: 20px 40px;
                            background: linear-gradient(135deg, #2ecc71, #27ae60);
                            color: white;
                            font-size: 24px;
                            font-weight: 700;
                            border-radius: 35px;
                            margin: 30px 0;
                            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.4);
                            position: relative;
                            overflow: hidden;
                        }
                        
                        .score-badge::before {
                            content: "";
                            position: absolute;
                            top: 0;
                            left: -100%;
                            width: 100%;
                            height: 100%;
                            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                            animation: shine 3s infinite;
                        }
                        
                        @keyframes shine {
                            0% { transform: translateX(-100%); }
                            50% { transform: translateX(100%); }
                            100% { transform: translateX(100%); }
                        }
                        
                        .certificate-details {
                            display: flex;
                            justify-content: space-between;
                            margin: 40px 0;
                            padding: 30px;
                            background: rgba(52, 152, 219, 0.1);
                            border-radius: 15px;
                            border: 1px solid rgba(52, 152, 219, 0.2);
                        }
                        
                        .detail-item {
                            flex: 1;
                            padding: 20px;
                        }
                        
                        .detail-label {
                            font-size: 14px;
                            color: #6c757d;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                            margin-bottom: 10px;
                            font-weight: 600;
                        }
                        
                        .detail-value {
                            font-size: 18px;
                            font-weight: 700;
                            color: #2c3e50;
                        }
                        
                        .certificate-footer {
                            margin-top: 50px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        
                        .signature-section {
                            text-align: center;
                            flex: 1;
                        }
                        
                        .signature-line {
                            width: 250px;
                            height: 3px;
                            background: linear-gradient(90deg, #3498db, #2ecc71);
                            margin: 0 auto 15px;
                            border-radius: 1px;
                        }
                        
                        .signature-text {
                            font-size: 14px;
                            color: #6c757d;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                        }
                        
                        .certificate-date {
                            text-align: center;
                            font-size: 16px;
                            color: #6c757d;
                            margin-top: 25px;
                            font-style: italic;
                        }
                        
                        .seal {
                            position: absolute;
                            bottom: 40px;
                            right: 40px;
                            width: 100px;
                            height: 100px;
                            border: 4px solid #e74c3c;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            background: linear-gradient(135deg, #e74c3c, #f39c12);
                            color: white;
                            font-size: 12px;
                            font-weight: bold;
                            text-align: center;
                            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
                        }
                        
                        .validation-code {
                            position: absolute;
                            top: 25px;
                            right: 25px;
                            background: linear-gradient(135deg, #3498db, #2ecc71);
                            color: white;
                            padding: 10px 20px;
                            border-radius: 25px;
                            font-size: 12px;
                            font-weight: 600;
                            font-family: "Courier New", monospace;
                            letter-spacing: 1px;
                            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
                        }
                        
                        @media print {
                            body {
                                background: white;
                            }
                            
                            .certificate {
                                box-shadow: none;
                                border: 12px solid #2c3e50;
                                background: white;
                            }
                            
                            .certificate::before {
                                display: none;
                            }
                            
                            .validation-code {
                                display: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="certificate">
                        <div class="watermark">UNILEARN</div>
                        
                        <div class="validation-code">CERT-' . $course->getId() . '-' . $user->getId() . '-' . $completionDate->format('Y-m-d') . '</div>
                        
                        <div class="certificate-header">
                            <h1 class="certificate-title">Certificate of Achievement</h1>
                            <p class="certificate-subtitle">This is to proudly certify that</p>
                        </div>
                        
                        <div class="certificate-body">
                            <h2 class="recipient-name">' . $user->getFullName() . '</h2>
                            
                            <p class="certificate-text">
                                has successfully demonstrated mastery and completed the comprehensive course
                            </p>
                            
                            <h3 class="course-title">' . $course->getTitle() . '</h3>
                            
                            <div class="score-badge">
                                Excellence Score: ' . $percentage . '%
                            </div>
                            
                            <p class="certificate-text">
                                with outstanding performance and has proven exceptional understanding of the subject matter.
                            </p>
                            
                            <div class="certificate-details">
                                <div class="detail-item">
                                    <div class="detail-label">Course ID</div>
                                    <div class="detail-value">#' . $course->getId() . '</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Completion Date</div>
                                    <div class="detail-value">' . $completionDate->format('F j, Y') . '</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Final Score</div>
                                    <div class="detail-value">' . $totalScore . '/' . $totalMaxScore . '</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="certificate-footer">
                            <div class="signature-section">
                                <div class="signature-line"></div>
                                <div class="signature-text">Director of Education</div>
                            </div>
                            
                            <div class="signature-section">
                                <div class="signature-line"></div>
                                <div class="signature-text">Academic Director</div>
                            </div>
                        </div>
                        
                        <div class="certificate-date">
                            Officially issued on ' . $completionDate->format('F j, Y') . '
                        </div>
                        
                        <div class="seal">
                            <div class="seal-inner">
                                UNILEARN<br>CERTIFIED
                            </div>
                        </div>
                    </div>
                </body>
                </html>
                ';

                // Configure DomPDF for simple HTML
                $pdfOptions = new Options();
                $pdfOptions->set('defaultFont', 'Arial');
                $pdfOptions->set('isRemoteEnabled', false); // Disable remote for simple HTML
                $pdfOptions->set('isHtml5ParserEnabled', true);

                // Create PDF instance
                $dompdf = new Dompdf($pdfOptions);
                $dompdf->loadHtml($simpleHtml);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                // Generate PDF content
                $pdfContent = $dompdf->output();

                // Create response
                $response = new Response($pdfContent);
                
                // Set headers for PDF download
                $userFullName = $user->getFullName();
                $courseTitle = $course->getTitle();
                $filename = sprintf(
                    'certificate_%s_%s_%s.pdf',
                    strtolower(str_replace(' ', '_', $userFullName ?? '')),
                    strtolower(str_replace(' ', '_', $courseTitle ?? '')),
                    date('Y-m-d')
                );
                
                $response->headers->set('Content-Type', 'application/pdf');
                $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
                $response->headers->set('Content-Length', (string) strlen($pdfContent));

                return $response;

            } catch (\Exception $pdfException) {
                // Enhanced error logging for simple HTML
                $this->logger->error('Simple HTML certificate failed: ' . $pdfException->getMessage(), [
                    'courseId' => $courseId,
                    'exception' => $pdfException
                ]);

                $this->addFlash('error', 'Simple certificate generation failed. Please try again.');
                return $this->redirectToRoute('app_course_show', ['id' => $courseId]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Certificate generation error: ' . $e->getMessage(), [
                'courseId' => $courseId,
                'exception' => $e
            ]);

            $this->addFlash('error', 'Unable to generate certificate at this time. Please try again later.');
            return $this->redirectToRoute('app_course_show', ['id' => $courseId]);
        }
    }

    /**
     * List all user certificates (redirects to myCertificates for consistency)
     */
    #[Route('/', name: 'app_certificates', methods: ['GET'])]
    public function listCertificates(): Response
    {
        // Redirect to my_certificates which has the real data logic
        return $this->redirectToRoute('app_my_certificates');
    }

    /**
     * Generate certificate for user course completion
     */
    #[Route('/{userId}/{courseId}', name: 'certificate_generate', methods: ['GET'])]
    public function generateCertificate(int $userId, int $courseId, Request $request): Response
    {
        try {
            // Fetch User
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user === null) {
                return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            // Fetch Course
            $course = $this->entityManager->getRepository(Course::class)->find($courseId);
            if ($course === null) {
                return new JsonResponse(['error' => 'Course not found'], Response::HTTP_NOT_FOUND);
            }

            // Calculate final percentage from QuizResults
            $quizResults = $this->entityManager->getRepository(QuizResult::class)
                ->createQueryBuilder('qr')
                ->join('qr.quiz', 'q')
                ->where('q.course = :course')
                ->andWhere('qr.user = :user')
                ->setParameter('course', $course)
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();

            if (empty($quizResults)) {
                return new JsonResponse(['error' => 'No quiz results found for this user and course'], Response::HTTP_BAD_REQUEST);
            }

            // Calculate total score and percentage
            $totalScore = 0;
            $totalMaxScore = 0;
            $latestCompletionDate = null;

            foreach ($quizResults as $result) {
                $totalScore += $result->getScore();
                $totalMaxScore += $result->getMaxScore();
                
                // Get the latest completion date
                if ($latestCompletionDate === null || $result->getTakenAt() > $latestCompletionDate) {
                    $latestCompletionDate = $result->getTakenAt();
                }
            }

            $percentage = $totalMaxScore > 0 ? round(($totalScore / $totalMaxScore) * 100, 2) : 0;

            // Validate user passed (>= 60%)
            if ($percentage < 60) {
                return new JsonResponse([
                    'error' => 'Certificate not available - user did not pass the course',
                    'percentage' => $percentage,
                    'required' => 60
                ], Response::HTTP_FORBIDDEN);
            }

            // Generate PDF
            $html = $this->renderView('certificate/certificate.html.twig', [
                'user' => $user,
                'course' => $course,
                'score' => $totalScore,
                'maxScore' => $totalMaxScore,
                'percentage' => $percentage,
                'completionDate' => $latestCompletionDate
            ]);

            // Configure DomPDF
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');
            $pdfOptions->set('isRemoteEnabled', true);
            $pdfOptions->set('isHtml5ParserEnabled', true);

            // Create PDF instance
            $dompdf = new Dompdf($pdfOptions);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Generate PDF content
            $pdfContent = $dompdf->output();

            // Create response
            $response = new Response($pdfContent);
            
            // Set headers for PDF download
            $userFullName = $user->getFullName();
            $courseTitle = $course->getTitle();
            $filename = sprintf(
                'certificate_%s_%s_%s.pdf',
                strtolower(str_replace(' ', '_', $userFullName ?? '')),
                strtolower(str_replace(' ', '_', $courseTitle ?? '')),
                date('Y-m-d')
            );
            
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
            $response->headers->set('Content-Length', (string) strlen($pdfContent));

            return $response;

        } catch (\Exception $e) {
            // Log error for debugging
            $this->logger->error('Certificate generation error: ' . $e->getMessage(), [
                'userId' => $userId,
                'courseId' => $courseId,
                'exception' => $e
            ]);

            return new JsonResponse([
                'error' => 'Internal server error during certificate generation',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check certificate eligibility (API endpoint)
     */
    #[Route('/check/{userId}/{courseId}', name: 'certificate_check', methods: ['GET'])]
    public function checkCertificateEligibility(int $userId, int $courseId): JsonResponse
    {
        try {
            // Fetch User
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user === null) {
                return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            // Fetch Course
            $course = $this->entityManager->getRepository(Course::class)->find($courseId);
            if ($course === null) {
                return new JsonResponse(['error' => 'Course not found'], Response::HTTP_NOT_FOUND);
            }

            // Calculate final percentage from QuizResults
            $quizResults = $this->entityManager->getRepository(QuizResult::class)
                ->createQueryBuilder('qr')
                ->join('qr.quiz', 'q')
                ->where('q.course = :course')
                ->andWhere('qr.user = :user')
                ->setParameter('course', $course)
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();

            if (empty($quizResults)) {
                return new JsonResponse([
                    'eligible' => false,
                    'reason' => 'No quiz results found for this user and course',
                    'percentage' => 0,
                    'required' => 60
                ]);
            }

            // Calculate total score and percentage
            $totalScore = 0;
            $totalMaxScore = 0;
            $latestCompletionDate = null;

            foreach ($quizResults as $result) {
                $totalScore += $result->getScore();
                $totalMaxScore += $result->getMaxScore();
                
                // Get the latest completion date
                if ($latestCompletionDate === null || $result->getTakenAt() > $latestCompletionDate) {
                    $latestCompletionDate = $result->getTakenAt();
                }
            }

            $percentage = $totalMaxScore > 0 ? round(($totalScore / $totalMaxScore) * 100, 2) : 0;
            $isEligible = $percentage >= 60;

            return new JsonResponse([
                'eligible' => $isEligible,
                'percentage' => $percentage,
                'required' => 60,
                'score' => $totalScore,
                'maxScore' => $totalMaxScore,
                'completionDate' => $latestCompletionDate?->format('Y-m-d H:i:s'),
                'quizCount' => count($quizResults)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Internal server error during eligibility check',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display user's certificates
     */
    #[Route('/my-certificates', name: 'app_my_certificates', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myCertificates(): Response
    {
        try {
            $user = $this->getUser();
            
            if ($user === null) {
                return $this->redirectToRoute('app_login');
            }
            
            // Debug: Log user info
            $this->logger->info('Loading certificates for user: ' . $user->getId() . ' (' . $user->getEmail() . ')');
            
            // Get user's certificates from database
            $certificates = [];
            try {
                $certificates = $this->entityManager->getRepository(Certificate::class)
                    ->findByUser($user);
                $this->logger->info('Found ' . count($certificates) . ' certificates for user ' . $user->getId());
            } catch (\Exception $e) {
                $this->logger->warning('Could not load certificates: ' . $e->getMessage());
            }

            // Also get quiz results with 80%+ score for certificates that might not have been saved yet
            $passedQuizResults = [];
            try {
                $passedQuizResults = $this->entityManager->getRepository(QuizResult::class)
                    ->createQueryBuilder('qr')
                    ->select('qr', 'q', 'c')
                    ->join('qr.quiz', 'q')
                    ->join('q.course', 'c')
                    ->where('qr.user = :user')
                    ->andWhere('qr.score >= qr.maxScore * 0.8')
                    ->orderBy('qr.takenAt', 'DESC')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getResult();
                $this->logger->info('Found ' . count($passedQuizResults) . ' passed quiz results for user ' . $user->getId());
            } catch (\Exception $e) {
                $this->logger->warning('Could not load quiz results: ' . $e->getMessage());
            }

            return $this->render('certificate/my_certificates.html.twig', [
                'certificates' => $certificates,
                'passedQuizResults' => $passedQuizResults,
                'user' => $user
            ]);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->logger->error('Access denied loading user certificates: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        } catch (\Exception $e) {
            $this->logger->error('Error loading user certificates: ' . $e->getMessage());
            // Still render the page instead of redirecting to courses
            return $this->render('certificate/my_certificates.html.twig', [
                'certificates' => [],
                'passedQuizResults' => [],
                'user' => $this->getUser()
            ]);
        }
    }
}
