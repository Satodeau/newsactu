<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Categorie;
use App\Entity\Commentary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ArticleController extends AbstractController
{
    /**
     * @Route("/voir/{cat_alias}/{article_alias}_{id}", name="show_article", methods={"GET"})
     */
    public function showArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        $commentaries = $entityManager->getRepository(Commentary::class)->findBy(['article' => $article->getId()]);

        return $this->render('article/show_article.html.twig', [
            'article' => $article,
            'commentaries' => $commentaries
        ]);
    }

    /**
     * @Route("/voir/{alias}", name="show_articles_from_category", methods={"GET"})
     */
    public function showArticlesFromCategory(Categorie $categorie, EntityManagerInterface $entityManager): Response
    {
        $articles = $entityManager->getRepository(Article::class)->findBy(['category'=> $categorie->getId(), 'deletedAt' => null]);

        return $this->render('article/show_articles_from_category.html.twig', [
            'articles' => $articles,
            'category' => $categorie
        ]);
    }
}