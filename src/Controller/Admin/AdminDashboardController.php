<?php

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use App\Service\SymfonyCacheRefresher;
use App\Service\VarnishPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(CategoryRepository $categoryRepository, EntityManagerInterface $em): Response
    {
        $itemCount = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM item');

        return $this->render('admin/dashboard.html.twig', [
            'categoryCount' => count($categoryRepository->findAll()),
            'itemCount' => $itemCount,
        ]);
    }

    #[Route('/varnish/purge-all', name: 'admin_varnish_purge_all', methods: ['POST'])]
    public function purgeAllVarnish(Request $request, VarnishPurger $varnishPurger): Response
    {
        if (!$this->isCsrfTokenValid('varnish-purge-all', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $varnishPurger->purgeAll();
        $this->addFlash('success', 'All list and category API cache entries were purged in Varnish.');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/symfony/refresh-cache', name: 'admin_symfony_refresh_cache', methods: ['POST'])]
    public function refreshSymfonyCache(Request $request, SymfonyCacheRefresher $cacheRefresher): Response
    {
        if (!$this->isCsrfTokenValid('symfony-refresh-cache', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_dashboard');
        }

        try {
            $result = $cacheRefresher->refreshAndRestartWeb();
            $this->addFlash('success', implode(' ', $result['messages']));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Cache refresh failed: '.$e->getMessage());
        }

        return $this->redirectToRoute('admin_dashboard');
    }
}
