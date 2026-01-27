<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/blog', name: 'app_blog_', requirements: ['locale' => 'fr|en|ko'])]
final class BlogController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
    )
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $articles = $this->articleRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('blog/index.html.twig', [
            'articles' => $articles,
        ]);
    }

    #[Route('/article/{id}-{slug}', name: 'article', requirements: ['id' => '\d+', 'slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $article = $this->articleRepository->find($id);

        return $this->render('blog/show.html.twig', [
            'article' => $article,
        ]);
    }
}
