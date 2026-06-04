<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemListPosition;
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
            ->leftJoin('i.categories', 'c')
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
            if ($item->getCategories()->isEmpty()) {
                $this->addFlash('error', 'Select at least one category.');

                return $this->redirectToRoute('admin_item_new');
            }

            $em->persist($item);
            $em->flush();

            [$categoryIds, $categoryNames] = $this->categoryMeta($item);

            try {
                $searchService->indexOne(
                    $item->getId(),
                    $item->getName(),
                    $categoryIds,
                    $categoryNames
                );
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Item saved in MySQL but Elasticsearch indexing failed.');
            }

            $varnishPurger->purgeAfterItemChange($item, $item->getCategories());
            $activityLogger->log('admin', 'item_create', sprintf('Item created: %s', $item->getName()), [
                'item_id' => $item->getId(),
                'category_ids' => $categoryIds,
                'list_position' => $item->getListPosition()->value,
            ]);
            $this->addFlash('success', $this->createdFlashMessage($item));

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
        $previousCategoryIds = $this->categoryIds($item);
        $previousPosition = $item->getListPosition();

        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Could not save the item. Please fix the errors below.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($item->getCategories()->isEmpty()) {
                $this->addFlash('error', 'Select at least one category.');

                return $this->redirectToRoute('admin_item_edit', ['id' => $item->getId()]);
            }

            $em->flush();

            [$categoryIds, $categoryNames] = $this->categoryMeta($item);

            try {
                $searchService->indexOne(
                    $item->getId(),
                    $item->getName(),
                    $categoryIds,
                    $categoryNames
                );
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Item saved in MySQL but Elasticsearch indexing failed.');
            }

            $this->purgeOnUpdate($varnishPurger, $item, $previousCategoryIds, $previousPosition);
            $activityLogger->log('admin', 'item_update', sprintf('Item updated: %s', $item->getName()), [
                'item_id' => $item->getId(),
                'category_ids' => $categoryIds,
                'previous_category_ids' => $previousCategoryIds,
                'list_position' => $item->getListPosition()->value,
            ]);

            $this->addFlash('success', 'Item updated. Varnish cache purged for affected pages.');

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

        $categories = $item->getCategories()->toArray();
        $listPosition = $item->getListPosition();
        $categoryIds = $this->categoryIds($item);
        $id = $item->getId();
        $em->remove($item);
        $em->flush();

        if ($id !== null) {
            $searchService->deleteOne($id);
        }

        $purgeItem = new Item();
        $purgeItem->setListPosition($listPosition);
        foreach ($categories as $category) {
            $purgeItem->addCategory($category);
        }
        $varnishPurger->purgeAfterItemChange($purgeItem, $categories);

        $activityLogger->log('admin', 'item_delete', sprintf('Item deleted #%d', $id), [
            'item_id' => $id,
            'category_ids' => $categoryIds,
        ]);
        $this->addFlash('success', 'Item deleted. Varnish cache purged for affected pages.');

        return $this->redirectToRoute('admin_items');
    }

    /** @return list<int> */
    private function categoryIds(Item $item): array
    {
        return array_values(array_filter(array_map(
            static fn (Category $c): ?int => $c->getId(),
            $item->getCategories()->toArray()
        )));
    }

    /**
     * @return array{0: list<int>, 1: list<string>}
     */
    private function categoryMeta(Item $item): array
    {
        $ids = [];
        $names = [];
        foreach ($item->getCategories() as $category) {
            if ($category->getId() !== null) {
                $ids[] = $category->getId();
            }
            $names[] = $category->getName();
        }

        return [$ids, $names];
    }

    private function createdFlashMessage(Item $item): string
    {
        if ($item->getListPosition() === ItemListPosition::First) {
            return 'Item created at the beginning. Full list cache purged for its categories.';
        }

        return 'Item created at the end. Only the last page cache was purged for its categories.';
    }

    /**
     * @param list<int> $previousCategoryIds
     */
    private function purgeOnUpdate(
        VarnishPurger $varnishPurger,
        Item $item,
        array $previousCategoryIds,
        ItemListPosition $previousPosition,
    ): void {
        $varnishPurger->purgeAfterItemChange($item, $item->getCategories());

        $removedIds = array_diff($previousCategoryIds, $this->categoryIds($item));
        if ($removedIds !== []) {
            $varnishPurger->purgeCategoryIds(...array_values($removedIds));
        }

        if ($previousPosition !== $item->getListPosition()) {
            $shadow = new Item();
            $shadow->setListPosition($previousPosition);
            foreach ($item->getCategories() as $category) {
                $shadow->addCategory($category);
            }
            $varnishPurger->purgeAfterItemChange($shadow, $item->getCategories());
        }
    }
}
