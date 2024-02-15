<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Service\Uploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    #[Route('/user/{id}', name: 'user')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function userProfile(User $user): Response
    {
        $currentUser = $this->getUser();

        if ($currentUser === $user) {
            return $this->redirectToRoute('current_user');
        }


        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/user}', name: 'current_user')]
    #[IsGranted('ROLE_USER')]
    public function currentUserProfile(Uploader $uploader, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var $user \App\Entity\User */
        $user = $this->getUser();

        $userForm = $this->createForm(UserType::class, $user);
        $userForm->remove('password');
        $userForm->add('newPassword', PasswordType::class, ['label' => 'Nouveau mot de passe', 'required' => false]);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {

            $picture = $userForm->get('pictureFile')->getData();

            if ($picture) {
                $user->setPicture($uploader->uploadProfileImage($picture, $user->getPicture()));
            }
            $newPassword = $user->getNewPassword();

            if ($newPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }

            $em->flush();
            $this->addFlash('success', 'Modification sauvegardée !');

            dump($user);
        }

        return $this->render('user/index.html.twig', [
            'form' => $userForm->createView(),
        ]);
    }
}
