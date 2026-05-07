<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\QuizResult;
use App\Entity\User;
use App\Entity\Certificate;
use App\Repository\UserRepository;
use App\Service\GeminiAiService;
use App\Service\GamificationService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/quiz')]
class QuizController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private UserRepository $userRepository;
    private GeminiAiService $aiService;
    private GamificationService $gamificationService;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, UserRepository $userRepository, GeminiAiService $aiService, GamificationService $gamificationService)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->userRepository = $userRepository;
        $this->aiService = $aiService;
        $this->gamificationService = $gamificationService;
    }

    #[Route('/add', name: 'quiz_add', methods: ['POST'])]
    public function addQuiz(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Debug: Log received data
            error_log('Received quiz data: '.print_r($data, true));

            // Validate required fields
            if (!isset($data['title']) || !isset($data['course_id'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quiz title and course ID are required',
                    'received_data' => $data,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get course
            $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
            if ($course === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Course not found',
                    'course_id' => $data['course_id'],
                ], Response::HTTP_NOT_FOUND);
            }

            // Create quiz
            $quiz = new Quiz();
            $quiz->setTitle($data['title']);
            $quiz->setCourse($course);
            
            // Set quiz type if provided
            if (isset($data['quiz_type'])) {
                $quiz->setQuizType($data['quiz_type']);
            }

            // Debug: Log quiz object before validation
            $quizCourse = $quiz->getCourse();
            error_log('Quiz object before validation: '.print_r([
                'title' => $quiz->getTitle(),
                'course_id' => $quizCourse !== null ? $quizCourse->getId() : null,
            ], true));

            // Validate quiz
            $errors = $this->validator->validate($quiz);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                    error_log('Validation error: '.$error->getMessage());
                }

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Save quiz
            $this->entityManager->persist($quiz);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Quiz created successfully',
                'quiz' => [
                    'id' => $quiz->getId(),
                    'title' => $quiz->getTitle(),
                    'course_id' => $course->getId(),
                ],
            ]);
        } catch (\Exception $e) {
            error_log('Exception in addQuiz: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/question/add', name: 'question_add', methods: ['POST'])]
    public function addQuestion(Request $request): JsonResponse
    {
        error_log('=== addQuestion called ===');

        try {
            $data = json_decode($request->getContent(), true);
            error_log('Received question data: '.print_r($data, true));

            // Validate required fields
            $requiredFields = ['quiz_id', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    error_log('Missing required field: '.$field);

                    return new JsonResponse([
                        'success' => false,
                        'message' => "Field '{$field}' is required and cannot be empty",
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Get quiz
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($data['quiz_id']);
            if ($quiz === null) {
                error_log('Quiz not found with ID: '.$data['quiz_id']);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quiz not found',
                ], Response::HTTP_NOT_FOUND);
            }

            error_log('Found quiz: '.$quiz->getTitle());

            // Create question
            $question = new Question();
            $question->setQuestion($data['question']);
            $question->setOptionA($data['option_a']);
            $question->setOptionB($data['option_b']);
            $question->setOptionC($data['option_c']);
            $question->setOptionD($data['option_d']);
            $question->setCorrectOption($data['correct_option']);
            $question->setQuiz($quiz);

            // Validate question
            $errors = $this->validator->validate($question);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Save question
            $this->entityManager->persist($question);
            $this->entityManager->flush();

            error_log('Question saved successfully with ID: '.$question->getId());

            return new JsonResponse([
                'success' => true,
                'message' => 'Question added successfully',
                'question' => [
                    'id' => $question->getId(),
                    'text' => $question->getQuestion(),
                    'options' => $question->getOptions(),
                    'correctOption' => $question->getCorrectOption(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/quiz/question/{id}', name: 'question_get', methods: ['GET'])]
    public function getQuestion(int $id): JsonResponse
    {
        try {
            $question = $this->entityManager->getRepository(Question::class)->find($id);
            if ($question === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Question not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $quiz = $question->getQuiz();

            return new JsonResponse([
                'success' => true,
                'question' => [
                    'id' => $question->getId(),
                    'text' => $question->getQuestion(),
                    'options' => $question->getOptions(),
                    'correctOption' => $question->getCorrectOption(),
                    'quiz' => [
                        'id' => $quiz !== null ? $quiz->getId() : null,
                        'title' => $quiz !== null ? $quiz->getTitle() : null,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/quiz/question/{id}/edit', name: 'question_edit', methods: ['PUT'])]
    public function editQuestion(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Get question
            $question = $this->entityManager->getRepository(Question::class)->find($id);
            if ($question === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Question not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Update question text
            if (isset($data['question'])) {
                $question->setQuestion($data['question']);
            }

            // Handle different question types
            if (isset($data['type']) && 'multiple' === $data['type']) {
                // Multiple choice question
                $question->setOptionA($data['optionA'] ?? '');
                $question->setOptionB($data['optionB'] ?? '');
                $question->setOptionC($data['optionC'] ?? '');
                $question->setOptionD($data['optionD'] ?? '');
                $question->setCorrectOption($data['correctOption'] ?? 'A');
            } elseif (isset($data['type']) && 'true_false' === $data['type']) {
                // True/False question - adapt to multiple choice format
                $question->setOptionA('True');
                $question->setOptionB('False');
                $question->setOptionC('False Option');
                $question->setOptionD('False Option');
                $question->setCorrectOption('true' === $data['correctAnswer'] ? 'A' : 'B');
            } else {
                // Short answer or default - adapt to multiple choice format
                $correctAnswer = $data['correctAnswer'] ?? 'Answer';
                $question->setOptionA($correctAnswer);
                $question->setOptionB('Wrong Option 1');
                $question->setOptionC('Wrong Option 2');
                $question->setOptionD('Wrong Option 3');
                $question->setCorrectOption('A');
            }

            // Update timestamp
            $question->setUpdatedAt(new \DateTimeImmutable());

            // Validate question
            $errors = $this->validator->validate($question);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Save changes
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Question updated successfully',
                'question' => [
                    'id' => $question->getId(),
                    'text' => $question->getQuestion(),
                    'options' => $question->getOptions(),
                    'correctOption' => $question->getCorrectOption(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/quiz/question/{id}/delete', name: 'question_delete', methods: ['DELETE'])]
    public function deleteQuestion(int $id): JsonResponse
    {
        try {
            $question = $this->entityManager->getRepository(Question::class)->find($id);
            if ($question === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Question not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($question);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Question deleted successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/courses', name: 'quiz_courses', methods: ['GET'])]
    public function getCourses(): JsonResponse
    {
        try {
            // Simulating logged-in teacher with ID 1 (same as InstructorController)
            // TODO: Replace with real authentication after user module integration:
            // $user = $this->getUser(); // or $this->security->getUser();
            // if (!$user) { throw $this->createAccessDeniedException('Please log in'); }
            // $teacher = $user; // assuming User entity implements TeacherInterface

            $loggedInTeacherId = 1;
            $teacher = $this->userRepository->find($loggedInTeacherId);

            // Get courses filtered by current user
            $courses = $this->entityManager->getRepository(Course::class)->findByUser($teacher);

            $courseData = [];
            foreach ($courses as $course) {
                $courseData[] = [
                    'id' => $course->getId(),
                    'title' => $course->getTitle(),
                    'name' => $course->getTitle(),
                    'status' => $course->getStatus(), // Add status field
                ];
            }

            return new JsonResponse([
                'success' => true,
                'courses' => $courseData,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/data', name: 'quiz_data', methods: ['GET'])]
    public function getQuizData(int $id): JsonResponse
    {
        try {
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($id);
            if ($quiz === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quiz not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $courseData = null;
            $quizCourse = $quiz->getCourse();
            if ($quizCourse !== null) {
                $courseData = [
                    'id' => $quizCourse->getId(),
                    'title' => $quizCourse->getTitle(),
                ];
            }

            // Get questions data
            $questionsData = [];
            foreach ($quiz->getQuestions() as $question) {
                $questionsData[] = [
                    'id' => $question->getId(),
                    'question' => $question->getQuestion(),
                    'option_a' => $question->getOptionA(),
                    'option_b' => $question->getOptionB(),
                    'option_c' => $question->getOptionC(),
                    'option_d' => $question->getOptionD(),
                    'correct_option' => $question->getCorrectOption(),
                ];
            }

            return new JsonResponse([
                'success' => true,
                'quiz' => [
                    'id' => $quiz->getId(),
                    'title' => $quiz->getTitle(),
                    'course' => $courseData,
                    'description' => '', // Quiz entity doesn't have description field
                    'timeLimit' => null, // Quiz entity doesn't have timeLimit field
                    'passingScore' => 70, // Quiz entity doesn't have passingScore field
                    'maxAttempts' => 3, // Quiz entity doesn't have maxAttempts field
                    'randomizeQuestions' => false, // Quiz entity doesn't have randomizeQuestions field
                    'showResults' => true, // Quiz entity doesn't have showResults field
                    'questionsCount' => $quiz->getQuestions()->count(),
                    'questions' => $questionsData,
                    'createdAt' => $quiz->getCreatedAt() !== null ? $quiz->getCreatedAt()->format('Y-m-d H:i:s') : null,
                    'updatedAt' => $quiz->getUpdatedAt() !== null ? $quiz->getUpdatedAt()->format('Y-m-d H:i:s') : null,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/edit', name: 'quiz_edit', methods: ['PUT'])]
    public function editQuiz(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Get quiz
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($id);
            if ($quiz === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quiz not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Update quiz fields
            if (isset($data['title'])) {
                $quiz->setTitle($data['title']);
            }

            if (isset($data['course_id'])) {
                $courseId = $data['course_id'];
                if (!empty($courseId)) {
                    $course = $this->entityManager->getRepository(Course::class)->find($courseId);
                    if ($course !== null) {
                        $quiz->setCourse($course);
                    }
                } else {
                    $quiz->setCourse(null);
                }
            }

            // Update timestamp
            $quiz->setUpdatedAt(new \DateTimeImmutable());

            // Validate quiz
            $errors = $this->validator->validate($quiz);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Save changes
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Quiz updated successfully',
                'quiz' => [
                    'id' => $quiz->getId(),
                    'title' => $quiz->getTitle(),
                    'course' => $quiz->getCourse() !== null ? [
                        'id' => $quiz->getCourse()->getId(),
                        'title' => $quiz->getCourse()->getTitle(),
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/delete', name: 'quiz_delete', methods: ['DELETE'])]
    public function deleteQuiz(int $id): JsonResponse
    {
        try {
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($id);
            if ($quiz === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quiz not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($quiz);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Quiz deleted successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/export', name: 'quiz_export', methods: ['GET'])]
    public function exportQuizzes(): Response
    {
        try {
            $quizzes = $this->entityManager->getRepository(Quiz::class)->findAll();

            // Create CSV content
            $csvContent = "Quiz ID,Quiz Title,Course,Questions Count,Created At,Updated At\n";

            foreach ($quizzes as $quiz) {
                $quizCourse = $quiz->getCourse();
                $courseTitle = $quizCourse !== null ? ($quizCourse->getTitle() ?? 'No Course') : 'No Course';
                $questionsCount = $quiz->getQuestions()->count();
                $createdAtObj = $quiz->getCreatedAt();
                $createdAt = $createdAtObj !== null ? $createdAtObj->format('Y-m-d H:i:s') : 'Unknown';
                $updatedAtObj = $quiz->getUpdatedAt();
                $updatedAt = $updatedAtObj !== null ? $updatedAtObj->format('Y-m-d H:i:s') : 'Never';

                // Escape commas and quotes in CSV
                $quizTitle = str_replace('"', '""', $quiz->getTitle() ?? '');
                $courseTitle = str_replace('"', '""', $courseTitle);

                $csvContent .= "\"{$quiz->getId()}\",\"{$quizTitle}\",\"{$courseTitle}\",\"{$questionsCount}\",\"{$createdAt}\",\"{$updatedAt}\"\n";
            }

            // Add questions details
            $csvContent .= "\n--- Questions Details ---\n";
            $csvContent .= "Quiz ID,Question ID,Question Text,Option A,Option B,Option C,Option D,Correct Option,Created At\n";

            foreach ($quizzes as $quiz) {
                foreach ($quiz->getQuestions() as $question) {
                    $questionText = str_replace('"', '""', $question->getQuestion() ?? '');
                    $optionA = str_replace('"', '""', $question->getOptionA() ?? '');
                    $optionB = str_replace('"', '""', $question->getOptionB() ?? '');
                    $optionC = str_replace('"', '""', $question->getOptionC() ?? '');
                    $optionD = str_replace('"', '""', $question->getOptionD() ?? '');
                    $createdAtObj = $question->getCreatedAt();
                    $createdAt = $createdAtObj !== null ? $createdAtObj->format('Y-m-d H:i:s') : 'Unknown';

                    $csvContent .= "\"{$quiz->getId()}\",\"{$question->getId()}\",\"{$questionText}\",\"{$optionA}\",\"{$optionB}\",\"{$optionC}\",\"{$optionD}\",\"{$question->getCorrectOption()}\",\"{$createdAt}\"\n";
                }
            }

            // Create response with CSV file
            $response = new Response($csvContent);
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="quiz-export-'.date('Y-m-d').'.csv"');

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Export failed: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/generate-questions', name: 'quiz_generate_questions', methods: ['POST'])]
    public function generateQuestions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields - support both course_id and course_title
            if (!isset($data['quiz_id'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quiz ID is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get quiz
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($data['quiz_id']);
            if ($quiz === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quiz not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Get course info - either from course_id or from quiz's course
            $course = null;
            if (isset($data['course_id'])) {
                $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
            } elseif ($quiz->getCourse() !== null) {
                $course = $quiz->getCourse();
            }

            if ($course === null && !isset($data['course_title'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Course not found or course_title not provided',
                ], Response::HTTP_BAD_REQUEST);
            }

            $courseTitle = $course !== null ? ($course->getTitle() ?? $data['course_title']) : $data['course_title'];
            $courseDescription = $course !== null ? ($course->getShortDescription() ?? $course->getDescription() ?? '') : ($data['course_description'] ?? '');
            $questionCount = $data['question_count'] ?? 5;
            $difficulty = $data['difficulty'] ?? 'medium';
            $language = $data['language'] ?? 'en';

            // Generate questions using AI
            $result = $this->aiService->generateQuizQuestions(
                $courseTitle,
                $courseDescription,
                $questionCount,
                $difficulty,
                $language
            );

            // Debug logging
            error_log('AI Service Result: ' . json_encode($result));

            if ($result['success']) {
                // Save generated questions to database
                $savedQuestions = [];
                foreach ($result['questions'] as $questionData) {
                    $question = new Question();
                    $question->setQuestion($questionData['question']);
                    $question->setOptionA($questionData['option_a']);
                    $question->setOptionB($questionData['option_b']);
                    $question->setOptionC($questionData['option_c']);
                    $question->setOptionD($questionData['option_d']);
                    $question->setCorrectOption($questionData['correct_option']);
                    $question->setQuiz($quiz);

                    $this->entityManager->persist($question);
                    $savedQuestions[] = [
                        'id' => $question->getId(),
                        'question' => $questionData['question'],
                        'options' => [
                            'A' => $questionData['option_a'],
                            'B' => $questionData['option_b'],
                            'C' => $questionData['option_c'],
                            'D' => $questionData['option_d']
                        ],
                        'correctOption' => $questionData['correct_option'],
                        'difficulty' => $questionData['difficulty'] ?? 'medium',
                        'topic' => $questionData['topic'] ?? 'General'
                    ];
                }

                $this->entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Questions generated and saved successfully',
                    'questions' => $savedQuestions,
                    'total_generated' => count($savedQuestions)
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => $result['message'] ?? 'Using sample questions due to AI service unavailability',
                    'fallback_questions' => $result['questions'] ?? []
                ], Response::HTTP_OK);
            }

        } catch (\Exception $e) {
            error_log('Exception in generateQuestions: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function generatePDFContent(array $quizzes): string
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management Report - '.date('Y-m-d').'</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #a855f7 100%);
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .header h1 {
            font-size: 3em;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
            margin: 15px 0 0 0;
            position: relative;
            z-index: 1;
            font-weight: 400;
        }
        
        .content {
            padding: 60px 40px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 35px 30px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed, #a855f7);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: 700;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            font-family: "JetBrains Mono", monospace;
        }
        
        .stat-label {
            color: #64748b;
            font-weight: 500;
            font-size: 1.1em;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .section-title {
            font-size: 2em;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 40px;
            text-align: center;
            position: relative;
        }
        
        .section-title::after {
            content: "";
            display: block;
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #a855f7);
            margin: 15px auto 0;
            border-radius: 2px;
        }
        
        .quiz-section {
            margin-bottom: 60px;
        }
        
        .quiz-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .quiz-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed, #a855f7);
            border-radius: 20px 20px 0 0;
        }
        
        .quiz-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        
        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .quiz-title {
            font-size: 1.6em;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            line-height: 1.3;
        }
        
        .quiz-id {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #64748b;
            padding: 8px 16px;
            border-radius: 12px;
            font-family: "JetBrains Mono", monospace;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .quiz-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 12px 20px;
            border-radius: 15px;
            font-size: 0.95em;
            color: #475569;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        
        .meta-item:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            transform: translateY(-1px);
        }
        
        .meta-item strong {
            color: #4f46e5;
            font-weight: 600;
        }
        
        .questions-grid {
            display: grid;
            gap: 25px;
        }
        
        .question-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-left: 4px solid #10b981;
            padding: 25px;
            border-radius: 16px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .question-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(16, 185, 129, 0.02) 100%);
            border-radius: 16px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .question-card:hover::before {
            opacity: 1;
        }
        
        .question-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .question-text {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 1.1em;
            line-height: 1.5;
        }
        
        .question-number {
            display: inline-block;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.85em;
            font-weight: 600;
            margin-right: 10px;
            font-family: "JetBrains Mono", monospace;
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .option {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .option:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .option.correct {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-color: #10b981;
        }
        
        .option.correct::before {
            content: "✓";
            position: absolute;
            top: 8px;
            right: 8px;
            background: #10b981;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .option-label {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85em;
            margin-right: 15px;
            font-weight: 600;
            font-family: "JetBrains Mono", monospace;
            min-width: 28px;
            text-align: center;
        }
        
        .option.correct .option-label {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .footer {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 40px;
            text-align: center;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            position: relative;
        }
        
        .footer::before {
            content: "";
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #a855f7);
            border-radius: 2px;
        }
        
        .footer p {
            margin: 5px 0;
            font-weight: 500;
        }
        
        .footer small {
            opacity: 0.7;
            font-size: 0.9em;
        }
        
        .no-questions {
            text-align: center;
            padding: 60px 40px;
            color: #94a3b8;
            font-style: italic;
            font-size: 1.1em;
        }
        
        .no-questions::before {
            content: "📝";
            display: block;
            font-size: 3em;
            margin-bottom: 20px;
            font-style: normal;
        }
        
        @media print {
            body { 
                background: white; 
                padding: 0;
            }
            .container { 
                box-shadow: none; 
                border-radius: 0;
            }
            .header::before {
                display: none;
            }
            .quiz-card:hover,
            .stat-card:hover,
            .question-card:hover {
                transform: none;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                border-radius: 16px;
            }
            .header {
                padding: 40px 20px;
            }
            .header h1 {
                font-size: 2em;
            }
            .content {
                padding: 30px 20px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }
            .quiz-card {
                padding: 25px;
            }
            .options-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Quiz Management Report</h1>
            <p>Generated on '.date('F j, Y, g:i A').'</p>
        </div>
        
        <div class="content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">'.count(array_filter($quizzes, function ($quiz) { return null !== $quiz->getCourse(); })).'</div>
                    <div class="stat-label">Total Quizzes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">'.array_reduce($quizzes, function ($carry, $quiz) { return $carry + $quiz->getQuestions()->count(); }, 0).'</div>
                    <div class="stat-label">Total Questions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">'.count(array_filter($quizzes, function ($quiz) { return null !== $quiz->getCourse(); })).'</div>
                    <div class="stat-label">With Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">'.(!empty($quizzes) ? max(array_map(function ($quiz) { return $quiz->getQuestions()->count(); }, $quizzes)) : 0).'</div>
                    <div class="stat-label">Max Questions</div>
                </div>
            </div>
            
            <div class="quiz-section">
                <h2 class="section-title">Quiz Details</h2>';

        foreach ($quizzes as $quiz) {
            $html .= '
                <div class="quiz-card">
                    <div class="quiz-header">
                        <h3 class="quiz-title">'.htmlspecialchars($quiz->getTitle()).'</h3>
                        <span class="quiz-id">#'.$quiz->getId().'</span>
                    </div>
                    
                    <div class="quiz-meta">
                        <div class="meta-item">
                            <strong>Course:</strong> '.($quiz->getCourse() ? htmlspecialchars($quiz->getCourse()->getTitle()) : 'No Course').'
                        </div>
                        <div class="meta-item">
                            <strong>Questions:</strong> '.$quiz->getQuestions()->count().'
                        </div>
                        <div class="meta-item">
                            <strong>Created:</strong> '.$quiz->getCreatedAt()->format('M j, Y').'
                        </div>
                    </div>
                    
                    <div class="questions-grid">';

            if ($quiz->getQuestions()->count() > 0) {
                foreach ($quiz->getQuestions() as $index => $question) {
                    $html .= '
                        <div class="question-card">
                            <div class="question-text">
                                <span class="question-number">Q'.($index + 1).'</span>
                                '.htmlspecialchars($question->getQuestion()).'
                            </div>
                            <div class="options-grid">
                                <div class="option '.('A' === $question->getCorrectOption() ? 'correct' : '').'">
                                    <span class="option-label">A</span>
                                    '.htmlspecialchars($question->getOptionA()).'
                                </div>
                                <div class="option '.('B' === $question->getCorrectOption() ? 'correct' : '').'">
                                    <span class="option-label">B</span>
                                    '.htmlspecialchars($question->getOptionB()).'
                                </div>
                                <div class="option '.('C' === $question->getCorrectOption() ? 'correct' : '').'">
                                    <span class="option-label">C</span>
                                    '.htmlspecialchars($question->getOptionC()).'
                                </div>
                                <div class="option '.('D' === $question->getCorrectOption() ? 'correct' : '').'">
                                    <span class="option-label">D</span>
                                    '.htmlspecialchars($question->getOptionD()).'
                                </div>
                            </div>
                        </div>';
                }
            } else {
                $html .= '<div class="no-questions">No questions added yet</div>';
            }

            $html .= '
                    </div>
                </div>';
        }

        $html .= '
            </div>
        </div>
        
        <div class="footer">
            <p>© '.date('Y').' UniLearn Quiz Management System</p>
            <small>Report generated on '.date('Y-m-d H:i:s').' | Beautiful HTML Export</small>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    #[Route('/translate-question', name: 'quiz_translate_question', methods: ['POST'])]
    public function translateQuestion(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (!isset($data['question_id']) || !isset($data['target_language'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Question ID and target language are required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $questionId = $data['question_id'];
            $targetLanguage = $data['target_language'];

            // Get question to verify ownership
            $question = $this->entityManager->getRepository(Question::class)->find($questionId);
            if ($question === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Question not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Prepare question data for translation
            $questionData = [
                'question' => $question->getQuestion(),
                'option_a' => $question->getOptionA(),
                'option_b' => $question->getOptionB(),
                'option_c' => $question->getOptionC(),
                'option_d' => $question->getOptionD(),
                'correct_option' => $question->getCorrectOption()
            ];

            // Translate question using AI
            $result = $this->aiService->translateQuestion($questionData, $targetLanguage);

            if ($result['success']) {
                $translatedData = $result['translated_data'];

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Question translated successfully',
                    'original_question' => [
                        'id' => $questionId,
                        'question' => $questionData['question'],
                        'options' => [
                            'A' => $questionData['option_a'],
                            'B' => $questionData['option_b'],
                            'C' => $questionData['option_c'],
                            'D' => $questionData['option_d']
                        ],
                        'correctOption' => $questionData['correct_option']
                    ],
                    'translated_question' => [
                        'question' => $translatedData['translated_question'],
                        'option_a' => $translatedData['translated_option_a'],
                        'option_b' => $translatedData['translated_option_b'],
                        'option_c' => $translatedData['translated_option_c'],
                        'option_d' => $translatedData['translated_option_d'],
                        'correct_option' => $translatedData['correct_option'],
                        'target_language' => $translatedData['target_language']
                    ]
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => $result['message'] ?? 'Using original question due to translation service unavailability',
                    'fallback_translation' => $result['translated_data'] ?? $questionData
                ], Response::HTTP_OK);
            }

        } catch (\Exception $e) {
            error_log('Exception in translateQuestion: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/save-translated-question', name: 'quiz_save_translated_question', methods: ['POST'])]
    public function saveTranslatedQuestion(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (!isset($data['question_id']) || !isset($data['translated_data'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Question ID and translated data are required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $questionId = $data['question_id'];
            $translatedData = $data['translated_data'];

            // Get original question
            $question = $this->entityManager->getRepository(Question::class)->find($questionId);
            if ($question === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Question not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Update question with translated content
            $question->setQuestion($translatedData['question']);
            $question->setOptionA($translatedData['option_a']);
            $question->setOptionB($translatedData['option_b']);
            $question->setOptionC($translatedData['option_c']);
            $question->setOptionD($translatedData['option_d']);
            $question->setCorrectOption($translatedData['correct_option']);
            $question->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Translated question saved successfully',
                'question' => [
                    'id' => $question->getId(),
                    'question' => $question->getQuestion(),
                    'options' => [
                        'A' => $question->getOptionA(),
                        'B' => $question->getOptionB(),
                        'C' => $question->getOptionC(),
                        'D' => $question->getOptionD()
                    ],
                    'correctOption' => $question->getCorrectOption()
                ]
            ]);

        } catch (\Exception $e) {
            error_log('Exception in saveTranslatedQuestion: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/take', name: 'quiz_take', methods: ['GET'])]
    public function takeQuiz(int $id): Response
    {
        try {
            // Debug: Log the request details
            error_log('Quiz take request received');
            error_log('Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
            error_log('Quiz ID: ' . $id);
            
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($id);
            if ($quiz === null) {
                error_log('Quiz not found for ID: ' . $id);
                throw $this->createNotFoundException('Quiz not found');
            }

            // Check if user has access to this quiz (through course enrollment)
            $user = $this->getUser();
            error_log('User object: ' . ($user !== null ? 'User found with ID: ' . $user->getId() : 'No user found'));
            error_log('User roles: ' . ($user !== null ? implode(', ', $user->getRoles()) : 'No user'));
            
            if ($user === null) {
                error_log('User not authenticated, redirecting to login');
                return $this->redirectToRoute('app_login');
            }

            $course = $quiz->getCourse();
            if ($course === null) {
                error_log('Quiz is not associated with a course');
                throw $this->createNotFoundException('Quiz is not associated with a course');
            }

            // Check if user is enrolled in the course
            $enrollment = $this->entityManager->getRepository(\App\Entity\Enrollment::class)
                ->findOneBy(['user' => $user, 'course' => $course]);
            
            if ($enrollment === null) {
                error_log('User not enrolled in course ID: ' . $course->getId());
                throw $this->createAccessDeniedException('You must be enrolled in the course to take this quiz');
            }

            // Use enrollment progress to check completion (same as my-courses page)
            if ($enrollment->getProgress() < 100) {
                $this->addFlash('warning', 'You must complete all lessons before taking the final quiz.');
                return $this->redirectToRoute('app_course_dashboard', ['id' => $course->getId()]);
            }

            // Get quiz questions
            $questions = $quiz->getQuestions()->toArray();
            
            if (empty($questions)) {
                error_log('Quiz has no questions for ID: ' . $id);
                $this->addFlash('error', 'This quiz has no questions yet.');
                return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
            }

            error_log('Rendering quiz take template for quiz ID: ' . $id);
            
            // Make quiz dynamic: randomize questions and shuffle answers
            $dynamicQuestions = $this->createDynamicQuiz($questions);
            
            return $this->render('quiz/take.html.twig', [
                'quiz' => $quiz,
                'course' => $course,
                'questions' => $dynamicQuestions,
            ]);
        } catch (\Exception $e) {
            error_log('Exception in takeQuiz: ' . $e->getMessage());
            $this->addFlash('error', 'An error occurred: ' . $e->getMessage());
            if (isset($course)) {
                return $this->redirectToRoute('app_course_dashboard', ['id' => $course->getId()]);
            }
            return $this->redirectToRoute('app_course_list');
        }
    }

    #[Route('/{id}/submit', name: 'quiz_submit', methods: ['POST'])]
    public function submitQuiz(int $id, Request $request): Response
    {
        try {
            $quiz = $this->entityManager->getRepository(Quiz::class)->find($id);
            if ($quiz === null) {
                throw $this->createNotFoundException('Quiz not found');
            }

            $user = $this->getUser();
            if ($user === null) {
                return $this->redirectToRoute('app_login');
            }

            $course = $quiz->getCourse();
            if ($course === null) {
                throw $this->createNotFoundException('Quiz is not associated with a course');
            }

            // Get submitted answers
            $submittedAnswers = $request->request->all('answers');
            
            if (empty($submittedAnswers)) {
                $this->addFlash('error', 'No answers submitted.');
                return $this->redirectToRoute('quiz_take', ['id' => $id]);
            }

            // Calculate score
            $questions = $quiz->getQuestions();
            $correctAnswers = 0;
            $totalQuestions = count($questions);
            $questionResults = [];

            foreach ($questions as $question) {
                $questionId = $question->getId();
                $userAnswer = $submittedAnswers[$questionId] ?? null;
                
                // Use the stored original correct option for validation
                $correctOption = $question->originalCorrectOption ?? $question->getCorrectOption();
                $isCorrect = $userAnswer === $correctOption;
                
                if ($isCorrect) {
                    $correctAnswers++;
                }

                $questionResults[] = [
                    'question' => $question->getQuestion(),
                    'userAnswer' => $userAnswer,
                    'correctAnswer' => $correctOption,
                    'isCorrect' => $isCorrect,
                    'options' => [
                        'A' => $question->getOptionA(),
                        'B' => $question->getOptionB(),
                        'C' => $question->getOptionC(),
                        'D' => $question->getOptionD(),
                    ]
                ];
            }

            $score = $correctAnswers;
            $maxScore = $totalQuestions;
            $percentage = round(($score / $maxScore) * 100, 2);

            // Save quiz result
            $quizResult = new QuizResult();
            if ($user instanceof \App\Entity\User) {
                $quizResult->setUser($user);
            }
            $quizResult->setQuiz($quiz);
            $quizResult->setScore($score);
            $quizResult->setMaxScore($maxScore);
            $quizResult->setTakenAt(new \DateTimeImmutable());

            $this->entityManager->persist($quizResult);
            $this->entityManager->flush();

            // Award XP based on quiz performance (don't let this break the quiz submission)
            try {
                if ($user instanceof \App\Entity\User) {
                    $this->gamificationService->awardQuizPassedXP($user, $quiz->getId(), $score, $maxScore);
                }
            } catch (\Exception $e) {
                error_log('Failed to award XP: ' . $e->getMessage());
                // Continue with quiz submission even if XP award fails
            }

            // Check if user passed (80% or higher)
            $passed = $percentage >= 80;
            
            error_log('Quiz submission - Score: ' . $score . '/' . $maxScore . ' (' . $percentage . '%), Passed: ' . ($passed ? 'true' : 'false'));

            if ($passed) {
                // Mark course as completed
                $enrollment = $this->entityManager->getRepository(\App\Entity\Enrollment::class)
                    ->findOneBy(['user' => $user, 'course' => $course]);
                
                if ($enrollment !== null) {
                    $enrollment->setCompletedAt(new \DateTimeImmutable());
                    $enrollment->setProgress(100);
                    $this->entityManager->flush();
                }

                // Redirect to certificate generation
                error_log('Redirecting to certificate with quiz result ID: ' . $quizResult->getId());
                return $this->redirectToRoute('quiz_certificate', ['id' => $quizResult->getId()]);
            } else {
                // Generate study recommendations
                $recommendations = $this->generateStudyRecommendations($questionResults, $course);
                
                return $this->render('quiz/result.html.twig', [
                    'quiz' => $quiz,
                    'course' => $course,
                    'score' => $score,
                    'maxScore' => $maxScore,
                    'percentage' => $percentage,
                    'passed' => $passed,
                    'recommendations' => $recommendations
                ]);
            }
        } catch (\Exception $e) {
            error_log('Exception in submitQuiz: ' . $e->getMessage());
            $this->addFlash('error', 'An error occurred: ' . $e->getMessage());
            if (isset($course)) {
                return $this->redirectToRoute('app_course_dashboard', ['id' => $course->getId()]);
            }
            return $this->redirectToRoute('app_course_list');
        }
    }

    #[Route('/result/{id}/certificate', name: 'quiz_certificate', methods: ['GET'])]
    public function generateCertificate(int $id): Response
    {
        try {
            error_log('Certificate generation requested for quiz result ID: ' . $id);
            
            $quizResult = $this->entityManager->getRepository(QuizResult::class)->find($id);
            if ($quizResult === null) {
                error_log('Quiz result not found for ID: ' . $id);
                throw $this->createNotFoundException('Quiz result not found');
            }

            $user = $this->getUser();
            $quizResultUser = $quizResult->getUser();
            if (!$user instanceof User || $quizResultUser === null || $quizResultUser->getId() !== $user->getId()) {
                error_log('Access denied for user ' . ($user instanceof User ? $user->getId() : 'null') . ' on quiz result ' . $id);
                throw $this->createAccessDeniedException('Access denied');
            }

            // Check if user passed the quiz (80% or higher)
            $percentage = $quizResult->getPercentage();
            
            $quiz = $quizResult->getQuiz();
            $course = $quiz !== null ? $quiz->getCourse() : null;
            
            if ($course === null) {
                error_log('Course not found for quiz result ID: ' . $id);
                throw $this->createNotFoundException('Course not found');
            }
            
            error_log('Rendering certificate for course: ' . $course->getTitle() . ', Score: ' . $percentage . '%');
            
            return $this->render('quiz/certificate.html.twig', [
                'user' => $user,
                'course' => $course,
                'quizResult' => $quizResult,
                'completionDate' => $quizResult->getTakenAt(),
                'errorMessage' => $percentage < 80 ? 'Certificates are only available for scores of 80% or higher.' : null
            ]);
        } catch (\Exception $e) {
            error_log('Exception in generateCertificate: ' . $e->getMessage());
            $this->addFlash('error', 'An error occurred: ' . $e->getMessage());
            return $this->redirectToRoute('app_my_certificates');
        }
    }

    #[Route('/result/{id}/download-pdf', name: 'quiz_download_pdf', methods: ['GET'])]
    public function downloadCertificatePdf(int $id): Response
    {
        try {
            $quizResult = $this->entityManager->getRepository(QuizResult::class)->find($id);
            if ($quizResult === null) {
                throw $this->createNotFoundException('Quiz result not found');
            }

            $user = $this->getUser();
            if ($user === null || $quizResult->getUser() !== $user) {
                throw $this->createAccessDeniedException('Access denied');
            }

            // Check if user passed the quiz (80% or higher)
            $percentage = $quizResult->getPercentage();
            if ($percentage < 80) {
                $this->addFlash('error', 'Certificates are only available for scores of 80% or higher.');
                $quizForRedirect = $quizResult->getQuiz();
                $courseForRedirect = $quizForRedirect !== null ? $quizForRedirect->getCourse() : null;
                if ($courseForRedirect !== null) {
                    return $this->redirectToRoute('app_course_dashboard', ['id' => $courseForRedirect->getId()]);
                }
                return $this->redirectToRoute('app_course_list');
            }

            $quiz = $quizResult->getQuiz();
            $course = $quiz !== null ? $quiz->getCourse() : null;
            
            if ($course === null) {
                throw $this->createNotFoundException('Course not found');
            }

            // Generate PDF content
            $html = $this->renderView('quiz/certificate_pdf.html.twig', [
                'user' => $user,
                'course' => $course,
                'quizResult' => $quizResult,
                'completionDate' => $quizResult->getTakenAt(),
            ]);

            // Configure DomPDF
            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            
            // Create DomPDF instance
            $dompdf = new Dompdf($options);
            
            // Set landscape orientation for certificate
            $dompdf->setPaper('A4', 'landscape');
            
            // Load HTML
            $dompdf->loadHtml($html);
            
            // Render PDF
            $dompdf->render();
            
            // Generate PDF filename
            $courseTitle = $course->getTitle() ?? 'Unknown';
            $filename = 'certificate-' . $courseTitle . '-' . $user->getId() . '-' . $quizResult->getId() . '.pdf';
            $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $filename) ?? 'certificate.pdf';
            
            // Save PDF to file system
            $projectDir = $this->getParameter('kernel.project_dir');
            $certificatesDir = (is_string($projectDir) ? $projectDir : '') . '/public/certificates/';
            $filePath = 'certificates/' . $filename;
            $fullPath = $certificatesDir . $filename;
            
            // Ensure directory exists
            if (!is_dir($certificatesDir)) {
                mkdir($certificatesDir, 0755, true);
            }
            
            // Save PDF file
            file_put_contents($fullPath, $dompdf->output());
            $fileSize = filesize($fullPath);
            if ($fileSize === false) {
                $fileSize = 0;
            }
            
            // Check if certificate already exists for this quiz result
            $certificate = $this->entityManager->getRepository(Certificate::class)
                ->findByQuizResult($quizResult);
            
            if ($certificate === null) {
                // Create new certificate record
                $certificate = new Certificate();
                $certificate->setUser($user);
                $certificate->setQuizResult($quizResult);
                $certificate->setCourse($course);
                $certificate->setFilename($filename);
                $certificate->setFilePath($filePath);
                $certificate->setFileSize($fileSize);
                $certificate->setGeneratedAt(new \DateTimeImmutable());
                
                $this->entityManager->persist($certificate);
            } else {
                // Update existing certificate
                $certificate->setFilename($filename);
                $certificate->setFilePath($filePath);
                $certificate->setFileSize($fileSize);
            }
            
            // Increment download count
            $certificate->incrementDownloadCount();
            
            $this->entityManager->flush();
            
            // Return PDF response
            return new Response(
                $dompdf->output(),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred: ' . $e->getMessage());
            return $this->redirectToRoute('app_my_courses');
        }
    }

    private function generateStudyRecommendations(array $questionResults, Course $course): array
    {
        $recommendations = [];
        $failedQuestions = array_filter($questionResults, fn($result) => !$result['isCorrect']);
        
        if (empty($failedQuestions)) {
            return $recommendations;
        }

        // Group failed questions by topic/lesson if possible
        $lessons = $this->entityManager->getRepository(\App\Entity\Lesson::class)
            ->findByCourse($course);

        foreach ($lessons as $lesson) {
            $recommendations[] = [
                'lesson' => $lesson,
                'reason' => 'Review this lesson to strengthen your understanding',
                'priority' => 'high'
            ];
        }

        // Add general recommendations
        $recommendations[] = [
            'lesson' => null,
            'reason' => 'Take more time to study the course materials before retaking the quiz',
            'priority' => 'medium'
        ];

        $recommendations[] = [
            'lesson' => null,
            'reason' => 'Consider reviewing the quiz questions you missed and understanding the correct answers',
            'priority' => 'high'
        ];

        return $recommendations;
    }

    private function createDynamicQuiz(array $questions): array
    {
        // Shuffle the order of questions
        shuffle($questions);
        
        // For each question, shuffle the answer options
        foreach ($questions as $question) {
            $shuffledAnswers = $this->shuffleQuestionAnswers($question);
            
            // Update the question with shuffled answers
            $question->setOptionA($shuffledAnswers['A']);
            $question->setOptionB($shuffledAnswers['B']);
            $question->setOptionC($shuffledAnswers['C']);
            $question->setOptionD($shuffledAnswers['D']);
            
            // Store the original correct option for validation
            $question->originalCorrectOption = $shuffledAnswers['correct'];
        }
        
        return $questions;
    }

    private function shuffleQuestionAnswers(\App\Entity\Question $question): array
    {
        // Get all answer options
        $options = [
            'A' => $question->getOptionA(),
            'B' => $question->getOptionB(),
            'C' => $question->getOptionC(),
            'D' => $question->getOptionD()
        ];
        
        // Get the original correct option
        $originalCorrect = $question->getCorrectOption();
        
        // Shuffle the options while keeping track of correct answer
        $optionKeys = ['A', 'B', 'C', 'D'];
        shuffle($optionKeys);
        
        $shuffledOptions = [];
        $newCorrectOption = null;
        
        foreach ($optionKeys as $i => $key) {
            $newKey = ['A', 'B', 'C', 'D'][$i];
            $shuffledOptions[$newKey] = $options[$key];
            
            // Find where the correct answer moved
            if ($key === $originalCorrect) {
                $newCorrectOption = $newKey;
            }
        }
        
        return [
            'A' => $shuffledOptions['A'],
            'B' => $shuffledOptions['B'],
            'C' => $shuffledOptions['C'],
            'D' => $shuffledOptions['D'],
            'correct' => $newCorrectOption
        ];
    }
}
