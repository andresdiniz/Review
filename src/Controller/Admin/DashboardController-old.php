<?php
// src/Controller/Admin/DashboardController.php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\Dashboard;  // atributo principal
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard as DashboardConfig;  // classe de configuração
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[Dashboard]  // ← apenas isso, sem routePath
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository $productRepository,
        private UserRepository $userRepository
    ) {}

    // NÃO use #[Route] aqui
    public function index(): Response
    {
        $productStats   = $this->productRepository->getStats();
        $totalUsers     = count($this->userRepository->findAll());
        $latestProducts = $this->productRepository->findLatest(5);

        return $this->render('admin/dashboard.html.twig', [
            'product_stats'   => $productStats,
            'total_users'     => $totalUsers,
            'latest_products' => $latestProducts,
        ]);
    }

    public function configureDashboard(): DashboardConfig
    {
        $logo = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ...>...</svg>';

        return DashboardConfig::new()
            ->setTitle($logo . 'Reviews')
            ->setFaviconPath('favicon.ico')
            ->setLocales(['pt_BR']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Produtos', 'fa fa-tag', Product::class);
        yield MenuItem::linkToCrud('Usuários', 'fa fa-user', User::class);
    }
}
