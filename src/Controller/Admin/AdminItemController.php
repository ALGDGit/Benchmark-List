<?php

namespace App\Controller\Admin;

use App\Entity\Item;
use App\Form\ItemType;
use App\Repository\ItemRepository;
use App\Service\Activity\ActivityLogger;
use App\Service\Elasticsearch\ItemSearchService;
use App\Service\VarnishPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/items')]
final class AdminItemController extends AbstractController
{
    #[Route('', name: 'admin_items')]
    public function index(Request $request, ItemRepository $repository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $qb = $repository->createQueryBuilder('i')
            ->innerJoin('i.category', 'c')
            ->addSelect('c')
            ->orderBy('i.id', 'DESC')
            ->setFirstResult(($page - 1) * 50)
            ->setMaxResults(50);

        return $this->render('admin/items/index.html.twig', [
            'items' => $qb->getQuery()->getResult(),
            'page' => $page,
        ]);
    }

    #[Route('/new', name: 'admin_item_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ItemSearchService $searchService,
        VarnishPurger $varnishPurger,
        ActivityLogger $activityLogger,
    ): Response {
        $item = new Item();
        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Could not save the item. Please fix the errors below.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $item->getCategory();
            if ($category === null) {
                $this->addFlash('error', 'Category is required.');

                return $this->redirectToRoute('admin_item_new');
            }

            $em->persist($item);
            $em->flush();

            try {
                $searchService->indexOne(
                    $item->getId(),
                    $item->getName(),
                    $category->getId(),
                    $category->getName()
                );
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Item saved in MySQL but Elasticsearch indexing failed.');
            }

            $varnishPurger->purgeSlotsForCategory($category->getId());
            $activityLogger->log('admin', 'item_create', sprintf('Item created: %s', $item->getName()), [
                'item_id' => $item->getId(),
                'category_id' => $category->getId(),
                'category' => $category->getName(),
            ]);
            $this->addFlash('success', 'Item created. It appears first on page 1 of its category.');

            return $this->redirectToRoute('admin_items');
        }

        return $this->render('admin/items/form.html.twig', [
            'form' => $form,
            'title' => 'New item',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_item_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Item $item,
        Request $request,
        EntityManagerInterface $em,
        ItemSearchService $searchService,
        VarnishPurger $varnishPurger,
        ActivityLogger $activityLogger,
    ): Response {
        $previousCategoryId = $item->getCategory()?->getId();

        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Could not save the item. Please fix the errors below.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $item->getCategory();
            if ($category === null) {
                $this->addFlash('error', 'Category is required.');

                return $this->redirectToRoute('admin_item_edit', ['id' => $item->getId()]);
            }

            $em->flush();

            try {
                $searchService->indexOne(
                    $item->getId(),
                    $item->getName(),
                    $category->getId(),
                    $category->getName()
                );
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Item saved in MySQL but Elasticsearch indexing failed.');
            }

            $categoryIds = array_filter([
                $category->getId(),
                $previousCategoryId !== $category->getId() ? $previousCategoryId : null,
            ]);
            $varnishPurger->purgeSlotsForCategories(...$categoryIds);
            $activityLogger->log('admin', 'item_update', sprintf('Item updated: %s', $item->getName()), [
                'item_id' => $item->getId(),
                'category_id' => $category->getId(),
                'previous_category_id' => $previousCategoryId,
            ]);

            $this->addFlash('success', 'Item updated. Cache purged only for affected lists.');

            return $this->redirectToRoute('admin_items');
        }

        return $this->render('admin/items/form.html.twig', [
            'form' => $form,
            'title' => 'Edit item',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_item_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Item $item,
        Request $request,
        EntityManagerInterface $em,
        ItemSearchService $searchService,
        VarnishPurger $varnishPurger,
        ActivityLogger $activityLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-item'.$item->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_items');
        }

        $categoryId = $item->getCategory()?->getId();
        $id = $item->getId();
        $em->remove($item);
        $em->flush();

        if ($id !== null) {
            $searchService->deleteOne($id);
        }
        if ($categoryId !== null) {
            $varnishPurger->purgeSlotsForCategory($categoryId);
        }
        $activityLogger->log('admin', 'item_delete', sprintf('Item deleted #%d', $id), [
            'item_id' => $id,
            'category_id' => $categoryId,
        ]);
        $this->addFlash('success', 'Item deleted. Cache purged only for affected lists.');

        return $this->redirectToRoute('admin_items');
    }
}
