<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use App\Service\Uploader;

class SecurityController extends AbstractController
{

    public function __construct(
        private FormLoginAuthenticator $authenticator
    ) {
    }

    #[Route('/signup', name: 'signup')]
    public function signup(Uploader $uploader, UserAuthenticatorInterface $userAuthenticator,  Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $userPasswordHasher, MailerInterface $mailer): Response
    {


        $user = new User();
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {

            $picture = $userForm->get('pictureFile')->getData();
            $user->setPicture($uploader->uploadProfileImage($picture));

            $user->setPassword($userPasswordHasher->hashPassword($user, $user->getPassword()));
            $em->persist($user);
            $em->flush();

            $email = new TemplatedEmail();
            $email->to($user->getEmail())
                ->subject('Bienvenue sur Wonder!')
                ->context([
                    'username' => $user->getFirstname()
                ])
                ->htmlTemplate('@email_templates/welcome.html.twig');

            $mailer->send($email);
            $this->addFlash('success', 'Bienvenue sur Wonder!');


            return $userAuthenticator->authenticateUser($user, $this->authenticator, $request);
        }

        return $this->render('security/signup.html.twig', [
            'form' => $userForm->createView(),
        ]);
    }

    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {

        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $username = $authenticationUtils->getLastUsername();


        return $this->render('security/login.html.twig', [
            'error' => $error,
            'username' => $username,

        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): Response
    {
        return $this->render('security/index.html.twig', [
            'controller_name' => 'SecurityController',
        ]);
    }


    #[Route('/reset-password/{token}', name: 'reset-password')]
    public function resetPassword(RateLimiterFactory $passwordRecoveryLimiter, UserPasswordHasherInterface $passwordHasher, Request $request, string $token, ResetPasswordRepository $resetPasswordRepo, EntityManagerInterface $em)
    {

        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Vous devez attendre 1h pour refaire une tentative.');
            return $this->redirectToRoute('login');
        }

        $resetPassword = $resetPasswordRepo->findOneBy(['token' => sha1($token)]);

        if (!$resetPassword || $resetPassword->getExpiredAt() < new \DateTime('now')) {
            if ($resetPassword) {
                $em->remove($resetPassword);
                $em->flush();
            } else {
                $this->addFlash('error', 'Demande expirée, veuillez recommencer.');
                return $this->redirectToRoute('login');
            }
        }


        $passwordForm = $this->createFormBuilder()
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit faire au minimum 6 caractères.'
                    ]),
                    new NotBlank([
                        'message' => 'Veuillez renseigner un mot de passe.'
                    ])
                ], 'label' => 'Nouveau mot de passe :'
            ],)->getForm();

        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $newPassword = $passwordForm->get('password')->getData();
            $user = $resetPassword->getUser();
            $hash = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hash);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifiée avec succès!');
            return $this->redirectToRoute('login');
        }
        return $this->render('security/reset_password_form.html.twig', [
            'form' => $passwordForm
        ]);
    }

    #[Route('/reset-password-request', name: 'reset-password-request')]
    public function resetPasswordRequest(RateLimiterFactory $passwordRecoveryLimiter, UserRepository $userRepo, Request $request, EntityManagerInterface $em, ResetPasswordRepository $resetPasswordRepo, MailerInterface $mailer)
    {
        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Vous devez attendre 1h pour refaire une tentative.');
            return $this->redirectToRoute('login');
        }


        $emailForm = $this->createFormBuilder()->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez entrer une adresse e-mail'
                ])
            ]
        ])->getForm();

        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $emailValue = $emailForm->get('email')->getData();
            $user = $userRepo->findOneBy(['email' => $emailValue]);

            if ($user) {

                $OldResetPassword = $resetPasswordRepo->findOneBy(['user' => $user]);

                if ($OldResetPassword) {
                    $em->remove($OldResetPassword);
                    $em->flush();
                }

                $resetPassword = new ResetPassword();
                $resetPassword->setUser($user);
                $resetPassword->setExpiredAt(new \DateTimeImmutable('+2 hours'));
                $token = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(30))), 0, 20);
                $resetPassword->setToken(sha1($token));

                $em->persist($resetPassword);
                $em->flush();

                $email = new TemplatedEmail();

                $email->to($emailValue)
                    ->subject('Demande de réinitialisation de mot de passe')
                    ->context(['token' => $token])
                    ->htmlTemplate('@email_templates/reset_password_request.html.twig');

                $mailer->send($email);
            }

            $this->addFlash('success', 'Un e-mail de réinitialisation vient de vous être envoyé!');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'form' => $emailForm->createView()
        ]);
    }
}
