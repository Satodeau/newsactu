<?php

namespace App\Controller;

use DateTime;
use App\Entity\User;
use App\Entity\Article;
use App\Entity\Categorie;
use App\Form\ArticleFormType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    /**
     * @Route ("/tableau-de-bord", name="show_dashboard", methods={"GET"})
     * @ // IsGranted("ROLE_ADMIN")
     */
    public function showDashboard(EntityManagerInterface $entityManager): Response
    {
        // try/catch fait partie du PHP nativement.
        // Cela a été créé pour gérer les class Exception (erreur.)
        // On se sert d'un try/catch lorsqu'on utilise des méthodes (fonctions) qui lance (throw) une Exception.
        

        try {
            
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

        }
        catch(AccessDeniedException $exception){
            $this->addFlash('warning', 'Cette partie du site est réservée.');
            return $this->redirectToRoute('default_home');
        }

        $articles = $entityManager->getRepository(Article::class)->findAll();
        $categories = $entityManager->getRepository(Categorie::class)->findAll();
        $users = $entityManager->getRepository(User::class)->findAll();


        return $this->render('admin/show_dashboard.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'users' => $users,
        ]);
            
    }


    /**
     * @Route("/creer-un-article", name="create_article", methods={"GET|POST"})
     */
    public function createArticle(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $article = new Article();

        $form = $this->createForm(ArticleFormType::class, $article)->handleRequest($request);

        // Traitement du formulaire.
        if($form->isSubmitted() && $form->isValid()){

            // Pour accéder à une valeur d'un input de $form, on fait : 
                // $form->get('title')->getData()

                // Setting des propriétés non mappées par le formulaire.
            $article->setAlias($slugger->slug($article->getTitle() ) );
            $article->setCreatedAt(new DateTime());
            $article->setUpdatedAt(new DateTime());

            // Association d'un auteur à un article
            $article->setAuthor($this->getUser());

            // Variabilisation du fichier 'photo' uploadé.
            $file = $form->get('photo')->getData();

            // if (isset(file) === true)
            // Si un fichier est uploadé (depuis le formulaire)
            if($file){
                // Maintenant il s'agit de reconstruire le nom du fichier pour le sécuriser.

                // 1ère étape: on déconstruit le nom du fichier et on variabilise.
                $extension = '.' . $file->guessExtension();
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                // Assainissement du nom de fichier
                // $safeFilnename = $slugger->slug($originalFilename);
                $safeFilnename = $article->getAlias();

                // 2ème étape : on reconstruit le nom du fichier maintenant qu'il est sécurisé (safe).
                $newFilename = $safeFilnename . '_' . uniqid() . $extension;

                try{
                    // On a configuré un paramètre 'uploads_dir' dans le fichier services.yaml
                    // Ce paramètre contient le chemin absolu de notre dossier d'upload de photo.
                    $file->move($this->getParameter('uploads_dir'), $newFilename);

                    // On set le NOM de la photo, pas le chemin
                    $article->setPhoto($newFilename);

                } catch (FileException $exception){

                }// END catch()
            }// END if($file)

            $entityManager->persist($article);
            $entityManager->flush();

            // Ici, on ajoute un message qu'on affichera en twig
            $this->addFlash('success', ' Bravo ,votre article est bien en ligne !');

            return $this->redirectToRoute('show_dashboard');
        }// END if ($form)

        return $this->render('admin/form/form_article.html.twig',[
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/modifier-un-article/{id}", name="update_article", methods={"GET|POST"})
     */
    public function updateArticle(Article $article, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {

        # Condition ternaire : $article->getPhoto() ?? ''
        # => est égal à : isset($article->getPhoto()) ? $article->getPhoto() : '' ;
        $originalPhoto = $article->getPhoto() ?? '' ;

        // 1er TOUR en méthode GET
        $form = $this->createForm(ArticleFormType::class, $article, [
            'photo' => $originalPhoto
        ])
        ->handleRequest($request);

        // 2ème TOUR de l'action en méthode POST
        if($form->isSubmitted() && $form->isValid()){
            $article->setAlias($slugger->slug($article->getTitle()));
            $article->setUpdatedAt(new DateTime());

            $file = $form->get('photo')->getData();

            if($file){

                $extension = '.' . $file->guessExtension();
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilnename = $article->getAlias();
                $newFilename = $safeFilnename . '_' . uniqid() . $extension;

                try{

                    $file->move($this->getParameter('uploads_dir'), $newFilename);

                    $article->setPhoto($newFilename);

                } catch (FileException $exception){
                    # code à exécuter si une erreur est attrapée.

                }// END catch()

                } else { 
                    $article->setPhoto($originalPhoto);
                }// END if($file)
            
                $entityManager->persist($article);
                $entityManager->flush();

                $this->addFlash('success', "L'article " . $article->getTitle() ." à bien été mofidié !");

                return $this->redirectToRoute("show_dashboard");
        } // END if($form)

        // On retourne la vue pour la méthode GET
        return $this->render('admin/form/form_article.html.twig', [
            'form' => $form->createView(),
            'article' => $article
        ]);
    }

    /**
     * @Route("/archiver-un-article/{id}", name="soft_delete_article", methods={"GET"})
     */
    public function softDeleteArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        $article->setDeletedAt(new DateTime());

        $entityManager->persist($article);
        $entityManager->flush();

        $this->addFlash ('success', "L'article ".  $article->getTitle() ." a bien été archivé");

        return $this->redirectToRoute('show_dashboard');
    }

    /**
     * @Route("/supprimer-un-article/{id}", name="hard_delete_article", methods={"GET"})
     */
    public function hardDeleteArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($article);
        $entityManager->flush();

        $this->addFlash ('success', "L'article "  .  $article->getTitle()  . " a bien été supprimé de la base de données");

        return $this->redirectToRoute('show_dashboard');
    }

    /**
     * @Route("/restaurer-un-article/{id}", name="restore_article", methods={"GET"})
     */
    public function restoreArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        $article->setDeletedAt();

        $entityManager->persist($article);
        $entityManager->flush();

        $this->addFlash ('success', "L'article " . $article->getTitle() . " a bien été restaurer");

        return $this->redirectToRoute('show_dashboard');

    }
}
