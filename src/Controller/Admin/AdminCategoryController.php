<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\Activity\ActivityLogger;
use App\Service\Slugger;
use App\Service\VarnishPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/categories')]
final class AdminCategoryController extends AbstractController
{
    #[Route('', name: 'admin_categories')]
    public function index(CategoryRepository $repository): Response
    {
        return $this->render('admin/categories/index.html.twig', [
            'categories' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_category_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        Slugger $slugger,
        ActivityLogger $activityLogger,
    ): Response {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($category->getSlug() === '') {
                $category->setSlug($slugger->slug($category->getName()));
            }
            $em->persist($category);
            $em->flush();
            $activityLogger->log('admin', 'category_create', sprintf('Category created: %s', $category->getName()), [
                'category_id' => $category->getId(),
            ]);
            $this->addFlash('success', 'Category created.');

            return $this->redirectToRoute('admin_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form' => $form,
            'title' => 'New category',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_category_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Category $category,
        Request $request,
        EntityManagerInterface $em,
        Slugger $slugger,
        VarnishPurger $varnishPurger,
        ActivityLogger $activityLogger,
    ): Response {
        $slugBefore = $category->getSlug();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($category->getSlug() === '') {
                $category->setSlug($slugger->slug($category->getName()));
            }
            $em->flush();
            if ($category->getId() !== null) {
                $varnishPurger->purgeCategoryIds($category->getId());
            }
            if ($slugBefore !== '' && $slugBefore !== $category->getSlug()) {
                $varnishPurger->purgeCategorySlug($slugBefore);
            }
            $activityLogger->log('admin', 'category_update', sprintf('Category updated: %s', $category->getName()), [
                'category_id' => $category->getId(),
            ]);
            $this->addFlash('success', 'Category updated. Varnish purged its cache tag.');

            return $this->redirectToRoute('admin_categories');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form' => $form,
            'title' => 'Edit category',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Category $category,
        Request $request,
        EntityManagerInterface $em,
        VarnishPurger $varnishPurger,
        ActivityLogger $activityLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-category'.$category->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_categories');
        }

        $categoryId = $category->getId();
        $categoryName = $category->getName();
        $categorySlug = $category->getSlug();
        if ($categoryId !== null) {
            $varnishPurger->purgeCategoryIds($categoryId);
        } elseif ($categorySlug !== '') {
            $varnishPurger->purgeCategorySlug($categorySlug);
        }
        $em->remove($category);
        $em->flush();
        $activityLogger->log('admin', 'category_delete', sprintf('Category deleted: %s', $categoryName), [
            'category_id' => $categoryId,
        ]);
        $this->addFlash('success', 'Category deleted. Varnish purged its cache tag.');

        return $this->redirectToRoute('admin_categories');
    }
}
