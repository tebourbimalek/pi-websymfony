<?php

namespace App\Controller;

use App\Entity\Chapter;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\LessonCompletion;
use App\Repository\ChapterRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonCompletionRepository;
use App\Service\GamificationService;
use App\Service\LearningPathService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LessonController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private EnrollmentRepository $enrollmentRepository;
    private LessonCompletionRepository $lessonCompletionRepository;
    private LearningPathService $learningPathService;
    private GamificationService $gamificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EnrollmentRepository $enrollmentRepository,
        LessonCompletionRepository $lessonCompletionRepository,
        LearningPathService $learningPathService,
        GamificationService $gamificationService
    ) {
        $this->entityManager = $entityManager;
        $this->enrollmentRepository = $enrollmentRepository;
        $this->lessonCompletionRepository = $lessonCompletionRepository;
        $this->learningPathService = $learningPathService;
        $this->gamificationService = $gamificationService;
    }

    #[Route('/lesson/{id}', name: 'app_lesson_show', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function show(Lesson $lesson, ChapterRepository $chapterRepository, Request $request): Response
    {
        $chapter = $lesson->getChapter();
        $course = $chapter !== null ? $chapter->getCourse() : null;

        if ($course === null) {
            throw $this->createNotFoundException('Course not found for this lesson');
        }

        $user = $this->getUser();
        
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }
        
        // Ensure user is the correct entity type
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_login');
        }
        
        // Check if user is the instructor/creator of this course
        $courseUser = $course->getUser();
        $isInstructor = $courseUser !== null && $courseUser->getId() === $user->getId();
        
        // Check if user has instructor role
        $hasInstructorRole = in_array('ROLE_INSTRUCTOR', $user->getRoles(), true);
        
        // Instructors can access their own course lessons without enrollment
        if ($isInstructor && $hasInstructorRole) {
            // Allow full access - skip enrollment check
            return $this->renderLesson($lesson, $chapter, $course, $user, null, $chapterRepository);
        }
        
        // Check if user is enrolled (for students and other users)
        $enrollment = $this->enrollmentRepository->findOneByUserAndCourse($user, $course);

        if ($enrollment === null) {
            $this->addFlash('error', 'You must enroll in this course to access lessons.');
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
        }

        // Get latest test result for learning path
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

        // Get learning path
        $learningPath = $this->learningPathService->getRecommendedStartingPoint($course, $latestTestResult);

        // Check if this lesson is accessible based on learning path
        // Allow access if: 1) It's the recommended starting lesson, 2) User has completed current lesson, 3) No learning path restriction, 4) It's the next lesson after a completed one, 5) User has completed this specific lesson, 6) It's the last completed lesson, or 7) User has completed any lesson up to this point
        if ($learningPath['lesson'] && 
            $learningPath['lesson']->getId() !== $lesson->getId() && 
            !$this->isLessonCompleted($user, $lesson) &&
            !$this->isNextLessonAfterCompleted($user, $lesson, $course) &&
            !$this->isLastCompletedLesson($user, $course) &&
            !$this->isLessonUpToCurrentPosition($user, $lesson, $course)) {
            // User is trying to access a lesson that's not their recommended starting point
            $this->addFlash('warning', 'Based on your test performance, you should start with: ' . $learningPath['lesson']->getTitle());
            return $this->redirectToRoute('app_lesson_show', ['id' => $learningPath['lesson']->getId()]);
        }

        // Get all chapters with their lessons for the sidebar
        $chapters = $this->entityManager->createQueryBuilder()
            ->select('c', 'l')
            ->from(Chapter::class, 'c')
            ->leftJoin('c.lessons', 'l')
            ->where('c.course = :course')
            ->setParameter('course', $course)
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        // Get previous and next lessons
        $previousLesson = $this->getAdjacentLesson($lesson, $course, 'previous');
        $nextLesson = $this->getAdjacentLesson($lesson, $course, 'next');

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'chapter' => $chapter,
            'course' => $course,
            'chapters' => $chapters,
            'previousLesson' => $previousLesson,
            'nextLesson' => $nextLesson,
            'enrollment' => $enrollment,
            'learningPath' => $learningPath,
            'isRecommendedLesson' => $learningPath['lesson'] && $learningPath['lesson']->getId() === $lesson->getId(),
            'completedLessons' => $this->lessonCompletionRepository->findByUserAndCourse($user, $course),
        ]);
    }

    private function getAdjacentLesson(Lesson $currentLesson, Course $course, string $direction = 'next'): ?Lesson
    {
        $currentChapter = $currentLesson->getChapter();
        if ($currentChapter === null) {
            return null;
        }
        $qb = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.chapter', 'c')
            ->where('c.course = :course')
            ->setParameter('course', $course);

        if ('next' === $direction) {
            $qb->andWhere('(c.id > :chapterId) OR (c.id = :chapterId AND l.sortOrder > :lessonOrder)')
               ->orderBy('c.id', 'ASC')
               ->addOrderBy('l.sortOrder', 'ASC');
        } else {
            $qb->andWhere('(c.id < :chapterId) OR (c.id = :chapterId AND l.sortOrder < :lessonOrder)')
               ->orderBy('c.id', 'DESC')
               ->addOrderBy('l.sortOrder', 'DESC');
        }

        $qb->setParameter('chapterId', $currentChapter->getId())
           ->setParameter('lessonOrder', $currentLesson->getSortOrder())
           ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Render lesson view - used for both instructors and students
     */
    private function renderLesson(Lesson $lesson, ?Chapter $chapter, Course $course, \App\Entity\User $user, ?\App\Entity\Enrollment $enrollment, ChapterRepository $chapterRepository): Response
    {
        // Get all chapters with their lessons for the sidebar
        $chapters = $this->entityManager->createQueryBuilder()
            ->select('c', 'l')
            ->from(Chapter::class, 'c')
            ->leftJoin('c.lessons', 'l')
            ->where('c.course = :course')
            ->setParameter('course', $course)
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        // Get previous and next lessons
        $previousLesson = $this->getAdjacentLesson($lesson, $course, 'previous');
        $nextLesson = $this->getAdjacentLesson($lesson, $course, 'next');
        
        // Check if user is instructor
        $courseUser = $course->getUser();
        $isInstructor = $courseUser !== null && $courseUser->getId() === $user->getId();

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'chapter' => $chapter,
            'course' => $course,
            'chapters' => $chapters,
            'previousLesson' => $previousLesson,
            'nextLesson' => $nextLesson,
            'enrollment' => $enrollment,
            'learningPath' => ['lesson' => null, 'message' => '', 'reason' => ''],
            'isRecommendedLesson' => true,
            'completedLessons' => $this->lessonCompletionRepository->findByUserAndCourse($user, $course),
            'isInstructor' => $isInstructor,
        ]);
    }

    private function isLessonCompleted(\App\Entity\User $user, Lesson $lesson): bool
    {
        // Check if lesson completion exists in database
        return $this->lessonCompletionRepository->isLessonCompleted($user, $lesson);
    }
    
    private function isNextLessonAfterCompleted(\App\Entity\User $user, Lesson $lesson, Course $course): bool
    {
        // Get all lessons in order
        $lessons = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.chapter', 'c')
            ->where('c.course = :course')
            ->setParameter('course', $course)
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Find the position of current lesson
        $currentPosition = 0;
        foreach ($lessons as $position => $lessonInCourse) {
            if ($lessonInCourse->getId() === $lesson->getId()) {
                $currentPosition = $position;
                break;
            }
        }
        
        // Check if this is the next lesson after the last completed lesson
        $completedLessons = $this->lessonCompletionRepository->findByUserAndCourse($user, $course);
        $lastCompletedPosition = -1;
        
        foreach ($lessons as $position => $lessonInCourse) {
            if ($this->isLessonCompleted($user, $lessonInCourse)) {
                $lastCompletedPosition = $position;
            }
        }
        
        // This lesson is accessible if it's the next one after the last completed
        return $currentPosition === $lastCompletedPosition + 1;
    }
    
    private function isLastCompletedLesson(\App\Entity\User $user, Course $course): bool
    {
        // Get all lessons in order
        $lessons = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.chapter', 'c')
            ->where('c.course = :course')
            ->setParameter('course', $course)
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Check if there are any completed lessons
        $completedLessons = $this->lessonCompletionRepository->findByUserAndCourse($user, $course);
        
        if (empty($completedLessons)) {
            return false;
        }
        
        // Find the last completed lesson by completion date
        $lastCompleted = $completedLessons[0]; // Already ordered by completed_at ASC
        
        // Check if this is the last lesson in the course
        $lastLessonInCourse = end($lessons);
        
        return $lastCompleted->getLesson()->getId() === $lastLessonInCourse->getId();
    }
    
    private function isLessonUpToCurrentPosition(\App\Entity\User $user, Lesson $lesson, Course $course): bool
    {
        // Get all lessons in order
        $lessons = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.chapter', 'c')
            ->where('c.course = :course')
            ->setParameter('course', $course)
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Find the position of current lesson
        $currentPosition = 0;
        foreach ($lessons as $position => $lessonInCourse) {
            if ($lessonInCourse->getId() === $lesson->getId()) {
                $currentPosition = $position;
                break;
            }
        }
        
        // Find the highest completed lesson position
        $highestCompletedPosition = -1;
        foreach ($lessons as $position => $lessonInCourse) {
            if ($this->isLessonCompleted($user, $lessonInCourse)) {
                $highestCompletedPosition = $position;
            }
        }
        
        // Allow access if this lesson is at or before the highest completed position
        return $currentPosition <= $highestCompletedPosition;
    }
    
    private function getLessonPosition(Lesson $lesson): int
    {
        $chapter = $lesson->getChapter();
        if ($chapter === null) {
            return 0;
        }
        $course = $chapter->getCourse();
        
        // Get all lessons in order
        $lessons = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.chapter', 'c')
            ->where('c.course = :course')
            ->setParameter('course', $course)
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Find the position of current lesson
        foreach ($lessons as $position => $lessonInCourse) {
            if ($lessonInCourse->getId() === $lesson->getId()) {
                return $position + 1; // 1-based indexing
            }
        }
        
        return 0; // Not found
    }

    #[Route('/lesson/{id}/complete', name: 'app_lesson_complete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function completeLesson(Lesson $lesson, Request $request): Response
    {
        $chapter = $lesson->getChapter();
        $course = $chapter !== null ? $chapter->getCourse() : null;

        if ($course === null) {
            return $this->json(['success' => false, 'message' => 'Course not found']);
        }

        $user = $this->getUser();
        
        // Check if user is the instructor/creator of this course
        $courseUser = $course->getUser();
        $isInstructor = $courseUser !== null && $user !== null && $courseUser->getId() === $user->getId();
        $hasInstructorRole = $user !== null && in_array('ROLE_INSTRUCTOR', $user->getRoles(), true);
        
        if (!($user instanceof \App\Entity\User)) {
            return $this->json(['success' => false, 'message' => 'User not authenticated']);
        }

        $enrollment = $this->enrollmentRepository->findOneByUserAndCourse($user, $course);

        // Instructors can complete their own course lessons without enrollment
        if ($enrollment === null && $isInstructor && $hasInstructorRole) {
            // Create enrollment for instructor if missing
            $enrollment = new \App\Entity\Enrollment();
            $enrollment->setUser($user);
            $enrollment->setCourse($course);
            $enrollment->setStatus('active');
            $enrollment->setProgress(0.0);
            $this->entityManager->persist($enrollment);
            $this->entityManager->flush();
        }

        if ($enrollment === null) {
            return $this->json(['success' => false, 'message' => 'Not enrolled in this course']);
        }

        // Check if lesson is already completed
        $existingCompletion = $this->lessonCompletionRepository->findOneByUserAndLesson($user, $lesson);
        
        if ($existingCompletion === null) {
            // Create new lesson completion record
            $lessonCompletion = new LessonCompletion();
            $lessonCompletion->setUser($user);
            $lessonCompletion->setLesson($lesson);
            $lessonCompletion->setCourse($course);
            $lessonCompletion->setCompletedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($lessonCompletion);
            
            // Award XP for lesson completion
            $lessonId = $lesson->getId();
            $lessonTitle = $lesson->getTitle();
            $this->gamificationService->awardLessonCompletionXP($user, $lessonId ?? 0, $lessonTitle ?? '');
        }

        // Update enrollment progress
        // Use DB query for total lessons (same as Java version) to avoid lazy-loading issues
        $totalLessons = count($this->entityManager->getRepository(\App\Entity\Lesson::class)
            ->findByCourse($course));
        
        if ($totalLessons <= 0) {
            $totalLessons = 1; // Prevent division by zero
        }
        
        $completedCount = count($this->lessonCompletionRepository->findByUserAndCourse($user, $course));
        $newProgress = min(($completedCount / $totalLessons) * 100, 100);
        
        $enrollment->setProgress($newProgress);
        
        // Mark as completed if 100%
        if ($newProgress >= 100) {
            $enrollment->setStatus('completed');
            $enrollment->setCompletedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true, 
            'message' => 'Lesson completed successfully!',
            'progress' => round($newProgress, 1),
            'completedCount' => $completedCount,
            'totalLessons' => $totalLessons,
            'isCompleted' => $newProgress >= 100,
            'xp_awarded' => $existingCompletion === null ? 10 : 0
        ]);
    }

    #[Route('/lesson/{id}/complete-course', name: 'app_lesson_complete_course', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function completeAllLessons(Lesson $lesson, Request $request): Response
    {
        $chapter = $lesson->getChapter();
        $course = $chapter !== null ? $chapter->getCourse() : null;

        if ($course === null) {
            return $this->json(['success' => false, 'message' => 'Course not found']);
        }

        $user = $this->getUser();

        if (!($user instanceof \App\Entity\User)) {
            return $this->json(['success' => false, 'message' => 'User not authenticated']);
        }

        $enrollment = $this->enrollmentRepository->findOneByUserAndCourse($user, $course);

        if ($enrollment === null) {
            return $this->json(['success' => false, 'message' => 'Not enrolled in this course']);
        }

        // Get ALL lessons in the course
        $allLessons = $this->entityManager->getRepository(\App\Entity\Lesson::class)
            ->findByCourse($course);

        // Mark ALL lessons as completed
        $newlyCompleted = 0;
        foreach ($allLessons as $courseLesson) {
            $existingCompletion = $this->lessonCompletionRepository->findOneByUserAndLesson($user, $courseLesson);
            if ($existingCompletion === null) {
                $lessonCompletion = new LessonCompletion();
                $lessonCompletion->setUser($user);
                $lessonCompletion->setLesson($courseLesson);
                $lessonCompletion->setCourse($course);
                $lessonCompletion->setCompletedAt(new \DateTimeImmutable());
                $this->entityManager->persist($lessonCompletion);
                $newlyCompleted++;
            }
        }

        // Update enrollment progress to 100%
        $enrollment->setProgress(100);
        $enrollment->setStatus('completed');
        $enrollment->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'All lessons completed! Course finished.',
            'progress' => 100,
            'completedCount' => count($allLessons),
            'totalLessons' => count($allLessons),
            'isCompleted' => true,
            'xp_awarded' => $newlyCompleted * 10
        ]);
    }
}
