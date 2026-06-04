<?php

namespace App\Controller\Admin;

use App\Entity\HomepageSlot;
use App\Form\HomepageSlotsType;
use App\Repository\CategoryRepository;
use App\Repository\HomepageSlotRepository;
use App\Service\Activity\ActivityLogger;
use App\Service\CacheTag;
use App\Service\VarnishPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/slots')]
final class AdminSlotController extends AbstractController
{
    #[Route('', name: 'admin_slots')]
    public function edit(
        Request $request,
        HomepageSlotRepository $slotRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em,
        VarnishPurger $varnishPurger,
        ActivityLogger $activityLogger,
    ): Response {
        $slots = $slotRepository->findAllOrderedBySlot();
        if (count($slots) < HomepageSlot::COUNT) {
            $defaultCategory = $categoryRepository->findOneBy([], ['id' => 'ASC']);
            for ($n = 1; $n <= HomepageSlot::COUNT; ++$n) {
                if ($slotRepository->find($n) === null) {
                    $slot = new HomepageSlot();
                    $slot->setSlotNumber($n);
                    $category = $categoryRepository->find($n) ?? $defaultCategory;
                    if ($category !== null) {
                        $slot->setCategory($category);
                    }
                    $em->persist($slot);
                }
            }
            $em->flush();
            $slots = $slotRepository->findAllOrderedBySlot();
        }

        $categoryBefore = [];
        foreach ($slots as $slot) {
            $categoryBefore[$slot->getSlotNumber()] = $slot->getCategory()?->getId();
        }

        $form = $this->createForm(HomepageSlotsType::class, ['slots' => $slots]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $purgedTags = [];
            foreach ($slots as $slot) {
                $slotNum = $slot->getSlotNumber();
                $categoryIdBefore = $categoryBefore[$slotNum] ?? null;
                $categoryIdAfter = $slot->getCategory()?->getId();
                if ($categoryIdBefore === $categoryIdAfter) {
                    continue;
                }

                $tags = [CacheTag::list($slotNum)];
                foreach ([$categoryIdBefore, $categoryIdAfter] as $categoryId) {
                    if (!is_int($categoryId)) {
                        continue;
                    }
                    $category = $categoryRepository->find($categoryId);
                    if ($category !== null && $category->getSlug() !== '') {
                        $tags[] = CacheTag::category($category->getSlug());
                    }
                }
                $varnishPurger->purgeTags(...array_values(array_unique($tags)));
                $purgedTags = array_merge($purgedTags, $tags);
            }

            $activityLogger->log('admin', 'slots_update', $purgedTags === []
                ? 'Homepage slots saved with no category change'
                : sprintf('Homepage slots changed; purged tag(s): %s', implode(', ', $purgedTags)), [
                'purged_tags' => $purgedTags,
                'slots' => array_map(static fn ($s) => [
                    'slot' => $s->getSlotNumber(),
                    'category_id' => $s->getCategory()?->getId(),
                    'category' => $s->getCategory()?->getName(),
                ], $slots),
            ]);

            $message = $purgedTags === []
                ? 'Lists saved (no cache changes).'
                : sprintf('Lists updated. Varnish purged cache tag(s): %s.', implode(', ', $purgedTags));
            $this->addFlash('success', $message);

            return $this->redirectToRoute('admin_slots');
        }

        return $this->render('admin/slots.html.twig', [
            'form' => $form,
        ]);
    }
}
