<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Form\RegistrationType;
use App\Service\FaceAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    private FaceAuthService $faceAuthService;

    public function __construct(FaceAuthService $faceAuthService)
    {
        $this->faceAuthService = $faceAuthService;
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        // Gestion des requêtes AJAX pour validation
        if ($request->isXmlHttpRequest()) {
            $form->submit($request->request->all());

            $errors = [];
            if (!$form->isValid()) {
                foreach ($form->all() as $child) {
                    foreach ($child->getErrors(true) as $error) {
                        $fieldName = $child->getName();
                        if (!isset($errors[$fieldName])) {
                            $errors[$fieldName] = [];
                        }
                        $errors[$fieldName][] = $error->getMessage();
                    }
                }
            }

            return new JsonResponse([
                'valid' => $form->isValid(),
                'errors' => $errors,
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Récupérer le rôle STUDENT (id = 3)
                $role = $entityManager
                    ->getRepository(Role::class)
                    ->find(3);

                if ($role === null) {
                    throw new \RuntimeException('Student role not found in database');
                }

                $user->setRole($role);
                $user->setCreatedAt(new \DateTime());

                // Hash du mot de passe
                $passwordData = $form->get('password')->getData();
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    is_array($passwordData) ? $passwordData['first'] : $passwordData
                );

                $user->setPassword($hashedPassword);

                $entityManager->persist($user);
                $entityManager->flush();

                // Handle face registration if image was provided
                $faceImage = $form->get('faceImage')->getData();
                
                if (is_string($faceImage) && $faceImage !== '' && $this->faceAuthService->isAvailable()) {
                    try {
                        $faceRegistered = $this->faceAuthService->registerFace($user, $faceImage);
                        if ($faceRegistered) {
                            $this->addFlash('success', 'Face recognition enabled! You can now log in with your face.');
                        }
                    } catch (\Exception $e) {
                    }
                } else {
                }

                // Add success message
                $this->addFlash('success', 'Registration successful! Please login.');

                // Redirection vers la page login
                return $this->redirectToRoute('app_login');
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                // Handle duplicate email error
                if (str_contains($e->getMessage(), 'UNIQ_8D93D649E7927C74') || str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->addFlash('account_exists', 'This email address is already registered. Please sign in to your account.');
                } else {
                    $this->addFlash('error', 'Registration failed: '.$e->getMessage());
                }
                error_log('Registration error: '.$e->getMessage());
                
                return $this->redirectToRoute('app_register');
            } catch (\Exception $e) {
                // Handle other exceptions
                $this->addFlash('error', 'Registration failed: '.$e->getMessage());
                error_log('Registration error: '.$e->getMessage());
                
                return $this->redirectToRoute('app_register');
            }
        }

        return $this->render('auth/sign-up.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
