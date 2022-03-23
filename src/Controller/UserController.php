<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegisterFormTypePhpType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/inscription", name="user_register", methods={"GET|POST"})
     */
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        // Si l'utilisateur est connecté, alors on le redirige.
        if($this->getUser()){
            $this->addFlash('warning', "Vous êtes déjà inscrit.");

            return $this->redirectToRoute('default_home');
        }


        $user = new User();
        $form = $this->createForm(RegisterFormTypePhpType::class, $user)
        ->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){

            $user->setRoles(['ROLE_USER']);
            $user->setCreatedAt(new DateTime());
            $user->setUpdatedAt(new DateTime());

            # Hashage du password en clair
            $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('sucess', 'Vous êtes maintenant inscrit. Bienvenue !');

            return $this->redirectToRoute('default_home');
        }
        
        return $this->render('user/register.html.twig', [
            'form' => $form->createView()
        ]);
    }
}