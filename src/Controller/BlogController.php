<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/blog', name: 'app_blog_', requirements: ['locale' => 'fr|en|ko'])]
final class BlogController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly TranslatorInterface $translator,
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
    public function show(Article $article): Response
    {
        return $this->render('blog/show.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/article/edit/{id}', name: 'article_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(Article $article): Response
    {
        return $this->render('blog/edit.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/article/save/{id}', name: 'article_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(Request $request, Article $article): Response
    {
        $payload = $request->request->all();
        dump($payload);
        $article->setTitle($payload['title']);
        $article->setAuthor($payload['author']);
        $article->setContent($payload['content']);

        $this->articleRepository->save($article, true);
        $this->addFlash('success', $this->translator->trans('Article saved successfully.'));

        return $this->json(['ok' => true]);
    }

    #[Route('/article/delete/{id}', name: 'article_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Article $article): Response
    {
        $this->articleRepository->remove($article, true);
        $this->addFlash('success', $this->translator->trans('Article deleted successfully.'));

        return $this->redirectToRoute('app_blog_index');
    }
}
