<?php
// src/Controller/Admin/DashboardController.php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository $productRepository,
        private UserRepository $userRepository,
        private AdminUrlGenerator $adminUrlGenerator
    ) {}

    public function index(): Response
    {
        $productStats   = $this->productRepository->getStats();
        $totalUsers     = count($this->userRepository->findAll());
        $latestProducts = $this->productRepository->findLatest(5);

        $templateParameters = [
            'product_stats'   => $productStats,
            'total_users'     => $totalUsers,
            'latest_products' => $latestProducts,
        ];

        return parent::index();
    }

    public function configureResponseParameters(): \EasyCorp\Bundle\EasyAdminBundle\Collection\KeyValueStore
    {
        $productStats   = $this->productRepository->getStats();
        $totalUsers     = count($this->userRepository->findAll());
        $latestProducts = $this->productRepository->findLatest(5);

        $params = parent::configureResponseParameters();
        $params->set('templatePath', 'admin/dashboard.html.twig');
        $params->set('product_stats',   $productStats);
        $params->set('total_users',     $totalUsers);
        $params->set('latest_products', $latestProducts);

        return $params;
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/img/logo.png" alt="Logo" style="height:30px"> Reviews')
            ->setFaviconPath('favicon.ico')
            ->setLocales(['pt_BR']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Produtos', 'fa fa-tag', Product::class)
            ->setController(ProductCrudController::class);
        yield MenuItem::linkToCrud('Usuários', 'fa fa-user', User::class)
            ->setController(UserCrudController::class);
    }
}
