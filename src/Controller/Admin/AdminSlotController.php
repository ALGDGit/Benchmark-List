<?php

namespace App\Controller\Admin;

use App\Entity\HomepageSlot;
use App\Form\HomepageSlotsType;
use App\Repository\CategoryRepository;
use App\Repository\HomepageSlotRepository;
use App\Service\Activity\ActivityLogger;
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

            $purged = [];
            foreach ($slots as $slot) {
                $slotNum = $slot->getSlotNumber();
                if (($categoryBefore[$slotNum] ?? null) !== $slot->getCategory()?->getId()) {
                    $varnishPurger->purgeListSlot($slotNum);
                    $purged[] = $slotNum;
                }
            }

            $activityLogger->log('admin', 'slots_update', $purged === []
                ? 'Homepage slots saved with no category change'
                : sprintf('Homepage slots changed; purge on list(s): %s', implode(', ', $purged)), [
                'purged_slots' => $purged,
                'slots' => array_map(static fn ($s) => [
                    'slot' => $s->getSlotNumber(),
                    'category_id' => $s->getCategory()?->getId(),
                    'category' => $s->getCategory()?->getName(),
                ], $slots),
            ]);

            $message = $purged === []
                ? 'Lists saved (no cache changes).'
                : sprintf('Lists updated. Cache purged only on list(s): %s.', implode(', ', $purged));
            $this->addFlash('success', $message);

            return $this->redirectToRoute('admin_slots');
        }

        return $this->render('admin/slots.html.twig', [
            'form' => $form,
        ]);
    }
}
