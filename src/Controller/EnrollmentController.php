<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Entity\LessonCompletion;
use App\Form\EnrollmentType;
use App\Repository\CertificateRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonCompletionRepository;
use App\Repository\LessonRepository;
use App\Service\GamificationService;
use App\Service\LearningPathService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/enrollment')]
class EnrollmentController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private EnrollmentRepository $enrollmentRepository;
    private LessonRepository $lessonRepository;
    private LessonCompletionRepository $lessonCompletionRepository;
    private LearningPathService $learningPathService;
    private GamificationService $gamificationService;
    private CertificateRepository $certificateRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        EnrollmentRepository $enrollmentRepository,
        LessonRepository $lessonRepository,
        LessonCompletionRepository $lessonCompletionRepository,
        LearningPathService $learningPathService,
        GamificationService $gamificationService,
        CertificateRepository $certificateRepository
    ) {
        $this->entityManager = $entityManager;
        $this->enrollmentRepository = $enrollmentRepository;
        $this->lessonRepository = $lessonRepository;
        $this->lessonCompletionRepository = $lessonCompletionRepository;
        $this->learningPathService = $learningPathService;
        $this->gamificationService = $gamificationService;
        $this->certificateRepository = $certificateRepository;
    }

    #[Route('/course/{id}/enroll', name: 'app_course_enroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enroll(Course $course, Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_login');
        }
        
        // Check if user is already enrolled
        $existingEnrollment = $this->enrollmentRepository->findOneByUserAndCourse($user, $course);

        if ($existingEnrollment !== null) {
            $this->addFlash('info', 'You are already enrolled in this course.');
            
            // If placement test not taken, redirect to it
            if (!$existingEnrollment->isPlacementTestTaken()) {
                return $this->redirectToRoute('app_placement_test_start', ['id' => $course->getId()]);
            }
            
            // Redirect to starting lesson or dashboard
            $startingLesson = $existingEnrollment->getStartingLesson();
            if ($startingLesson !== null) {
                return $this->redirectToRoute('app_lesson_show', ['id' => $startingLesson->getId()]);
            }
            return $this->redirectToRoute('app_course_dashboard', ['id' => $course->getId()]);
        }

        // Create new enrollment
        $enrollment = new Enrollment();
        $enrollment->setUser($user);
        $enrollment->setCourse($course);
        $enrollment->setStatus('active');
        $enrollment->setProgress(0.0);
        
        // Add additional tracking information
        $enrollment->setIpAddress($request->getClientIp());
        $enrollment->setUserAgent($request->headers->get('User-Agent'));
        $enrollment->setEnrolledAt(new \DateTimeImmutable());

        // Create and handle form
        $form = $this->createForm(EnrollmentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Add course price to instructor's income
            $instructor = $course->getUser();
            if ($instructor !== null) {
                $price = $course->getPrice();
                $instructor->addIncome($price ?? 0.0);
            }
            
            $this->entityManager->persist($enrollment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Successfully enrolled in ' . $course->getTitle() . '!');

            // Redirect to placement test
            return $this->redirectToRoute('app_placement_test_start', ['id' => $course->getId()]);
        }

        // If form is not valid, redirect back to course page
        $this->addFlash('error', 'There was an error enrolling in the course. Please try again.');
        return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
    }

    #[Route('/course/{id}/dashboard', name: 'app_course_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function dashboard(Course $course): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_login');
        }
        
        // Check if user is enrolled
        $enrollment = $this->enrollmentRepository->findOneBy([
            'user' => $user,
            'course' => $course
        ]);

        if ($enrollment === null) {
            $this->addFlash('error', 'You must enroll in this course first.');
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
        }

        // Get all lessons for this course
        $lessons = $this->lessonRepository->findByCourse($course);
        
        // Get lesson completions for this user and course
        $completedLessons = $this->lessonCompletionRepository->findByUserAndCourse($user, $course);
        
        // Create an associative array of completed lesson IDs for quick lookup
        $completedLessonIds = [];
        foreach ($completedLessons as $completion) {
            $completedLessonIds[] = $completion->getLesson()->getId();
        }
        
        // Calculate detailed progress
        $totalLessons = count($lessons);
        $completedCount = count($completedLessons);
        $progressPercentage = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100, 1) : 0;
        
        // Get latest test result for this user and course
        $latestTestResult = $this->entityManager->getRepository('App\Entity\CourseTestResult')
            ->createQueryBuilder('ctr')
            ->leftJoin('ctr.courseTest', 'ct')
            ->leftJoin('ctr.user', 'u')
            ->where('u.id = :user AND ct.course = :course')
            ->setParameter('user', $user)
            ->setParameter('course', $course)
            ->orderBy('ctr.timeTaken', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        // Get intelligent learning path
        $learningPath = $this->learningPathService->getRecommendedStartingPoint($course, $latestTestResult);
        $learningProgress = $this->learningPathService->getLearningProgress($course, $latestTestResult);

        // Check for course completion quiz
        $courseQuiz = null;
        foreach ($course->getQuizzes() as $quiz) {
            $courseQuiz = $quiz;
            break; // Get first quiz
        }

        // Check if user has passed the quiz
        $quizPassed = false;
        if ($courseQuiz !== null) {
            $quizResult = $this->entityManager->getRepository('App\Entity\QuizResult')
                ->findOneBy([
                    'user' => $user,
                    'quiz' => $courseQuiz
                ], ['takenAt' => 'DESC']);
            
            if ($quizResult !== null && $quizResult->getPercentage() >= 80) {
                $quizPassed = true;
            }
        }

        return $this->render('enrollment/dashboard.html.twig', [
            'course' => $course,
            'enrollment' => $enrollment,
            'lessons' => $lessons,
            'completedLessons' => $completedLessons,
            'completedLessonIds' => $completedLessonIds,
            'progress' => $progressPercentage,
            'totalLessons' => $totalLessons,
            'completedCount' => $completedCount,
            'learningPath' => $learningPath,
            'learningProgress' => $learningProgress,
            'courseQuiz' => $courseQuiz,
            'quizPassed' => $quizPassed,
        ]);
    }

    private function redirectToFirstLesson(Course $course, \App\Entity\User $user): Response
    {
        // Get the first lesson of the course
        $firstLesson = $this->lessonRepository->findFirstLessonByCourse($course);

        if ($firstLesson !== null) {
            // Redirect to first lesson
            return $this->redirectToRoute('app_lesson_show', ['id' => $firstLesson->getId()]);
        } else {
            // If no lessons, redirect to course dashboard
            return $this->redirectToRoute('app_course_dashboard', ['id' => $course->getId()]);
        }
    }

    #[Route('/lesson/{id}/complete', name: 'app_lesson_complete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function completeLesson(Lesson $lesson, Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'You must be logged in'], 401);
        }
        
        $course = $lesson->getCourse();
        if ($course === null) {
            return $this->json(['error' => 'Course not found'], 404);
        }
        
        // Check if user is enrolled in the course
        $enrollment = $this->enrollmentRepository->findOneByUserAndCourse($user, $course);
        
        if ($enrollment === null) {
            return $this->json(['error' => 'You must be enrolled in this course'], 403);
        }

        // Check if lesson is already completed
        $existingCompletion = $this->lessonCompletionRepository->findOneBy(['lesson' => $lesson, 'user' => $user]);
        
        if ($existingCompletion !== null) {
            return $this->json(['error' => 'Lesson already completed'], 400);
        }

        // Create lesson completion record
        $lessonCompletion = new LessonCompletion();
        $lessonCompletion->setUser($user);
        $lessonCompletion->setLesson($lesson);
        $lessonCompletion->setCourse($lesson->getCourse());
        $lessonCompletion->setCompletedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($lessonCompletion);
        $this->entityManager->flush();

        // Award XP for lesson completion
        $lessonId = $lesson->getId();
        $lessonTitle = $lesson->getTitle();
        if ($lessonId !== null && $lessonTitle !== null) {
            $this->gamificationService->awardLessonCompletionXP($user, $lessonId, $lessonTitle);
        }

        // Update enrollment progress
        $this->updateEnrollmentProgress($enrollment);

        return $this->json([
            'success' => true,
            'message' => 'Lesson completed successfully!',
            'xp_awarded' => 10
        ]);
    }

    private function updateEnrollmentProgress(Enrollment $enrollment): void
    {
        $course = $enrollment->getCourse();
        $user = $enrollment->getUser();
        
        if ($course === null || $user === null) {
            return;
        }
        
        // Get all lessons for this course
        $lessons = $this->lessonRepository->findByCourse($course);
        
        // Get lesson completions for this user and course
        $completedLessons = $this->lessonCompletionRepository->findByUserAndCourse($user, $course);
        
        // Calculate progress
        $totalLessons = count($lessons);
        $completedCount = count($completedLessons);
        $progressPercentage = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100, 1) : 0;
        
        $enrollment->setProgress($progressPercentage);
        
        // Check if course is completed (all lessons done)
        $status = $enrollment->getStatus();
        if ($progressPercentage >= 100 && ($status === '' || $status === 'active')) {
            $enrollment->setStatus('completed');
            $enrollment->setCompletedAt(new \DateTimeImmutable());
            
            // Award XP for course completion
            $courseId = $course->getId();
            $courseTitle = $course->getTitle();
            if ($courseId !== null && $courseTitle !== null) {
                $this->gamificationService->awardCourseCompletionXP($user, $courseId, $courseTitle);
            }
            
            // Check if this is user's first completed course
            $userEnrollments = $this->enrollmentRepository->findByUser($user);
            $completedCourses = array_filter($userEnrollments, function($e) {
                return $e->getProgress() >= 100;
            });
            
            if (count($completedCourses) === 1) {
                $this->gamificationService->awardFirstCourseXP($user);
            }
        }
        
        $this->entityManager->flush();
    }

    #[Route('/my-courses', name: 'app_my_courses', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myCourses(): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_login');
        }
        
        $enrollments = $this->enrollmentRepository->findByUser($user);

        // Prepare enrollment data with completion/certificate/quiz status
        $enrollmentData = [];
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->getCourse();

            // Skip if course is null (orphaned enrollment)
            if ($course === null) {
                continue;
            }

            // Enrollment may point to a deleted course. Resolve it by id to avoid initializing a broken proxy.
            $courseId = $course->getId();
            if ($courseId === null) {
                continue;
            }

            // Check existence without initializing the proxy / relying on identity map
            $exists = (bool) $this->entityManager->getConnection()->fetchOne(
                'SELECT 1 FROM course WHERE id = ?',
                [$courseId]
            );
            if (!$exists) {
                continue;
            }
            
            // Check if course is complete - recalculate progress from DB
            $totalLessons = count($this->entityManager->getRepository(\App\Entity\Lesson::class)
                ->findByCourse($course));
            $completedCount = count($this->lessonCompletionRepository->findByUserAndCourse($user, $course));
            $actualProgress = $totalLessons > 0 ? min(($completedCount / $totalLessons) * 100, 100) : 0;
            
            // Update stored progress if different
            if (abs($actualProgress - $enrollment->getProgress()) > 0.1) {
                $enrollment->setProgress($actualProgress);
                if ($actualProgress >= 100 && $enrollment->getStatus() !== 'completed') {
                    $enrollment->setStatus('completed');
                    $enrollment->setCompletedAt(new \DateTimeImmutable());
                }
                $this->entityManager->flush();
            }
            
            $isComplete = $actualProgress >= 100 || $enrollment->getStatus() === 'completed';
            
            // Check if user has certificate for this course (with error handling for orphaned certificates)
            $certificate = null;
            $hasCertificate = false;
            try {
                $certificate = $this->certificateRepository->findOneByUserAndCourse($user, $course);
                $hasCertificate = $certificate !== null;
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                // Certificate references a non-existent course, skip it
                $hasCertificate = false;
            }
            
            // Check if course has quizzes available (use DB query instead of lazy-loaded collection)
            $courseQuiz = null;
            $canTakeQuiz = false;
            $quizPassed = false;
            try {
                $courseQuiz = $this->entityManager->getRepository(\App\Entity\Quiz::class)
                    ->findOneBy(['course' => $course]);
                $canTakeQuiz = $courseQuiz !== null;
                
                // Check if user already passed the quiz
                if ($canTakeQuiz) {
                    $quizResult = $this->entityManager->getRepository(\App\Entity\QuizResult::class)
                        ->createQueryBuilder('qr')
                        ->where('qr.user = :user')
                        ->andWhere('qr.quiz = :quiz')
                        ->andWhere('qr.score >= qr.maxScore * 0.8')
                        ->setParameter('user', $user)
                        ->setParameter('quiz', $courseQuiz)
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                    $quizPassed = $quizResult !== null;
                }
            } catch (\Exception $e) {
                $canTakeQuiz = false;
                $quizPassed = false;
            }
            
            $firstQuiz = $courseQuiz;
            
            $enrollmentData[] = [
                'enrollment' => $enrollment,
                'isComplete' => $isComplete,
                'hasCertificate' => $hasCertificate,
                'canTakeQuiz' => $canTakeQuiz,
                'quizPassed' => $quizPassed,
                'firstQuiz' => $firstQuiz,
                'certificate' => $certificate,
                'actualProgress' => $actualProgress,
            ];
        }

        return $this->render('enrollment/my-courses.html.twig', [
            'enrollmentData' => $enrollmentData,
        ]);
    }
}
