<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{
    #[Route('/categories', name: 'app_categories')]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAllOrdered(),
        ]);
    }

    #[Route('/category/{slug}', name: 'app_category_show', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function show(string $slug, CategoryRepository $categoryRepository, ItemRepository $itemRepository): Response
    {
        $category = $categoryRepository->findOneBySlug($slug);
        if ($category === null) {
            throw new NotFoundHttpException('Category not found.');
        }

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'totalItems' => $itemRepository->countByCategory($category),
        ]);
    }
}
