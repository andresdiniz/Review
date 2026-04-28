<?php
// src/Controller/Admin/DashboardController.php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository $productRepository,
        private UserRepository $userRepository
    ) {}

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

    public function configureDashboard(): Dashboard
    {
        // SVG inline — elimina o 404 de /img/logo.png
        $logo = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"
            xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;margin-right:6px">
            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02
                     L7 14.14L2 9.27L8.91 8.26L12 2Z"
                  fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"
                  stroke-linejoin="round"/>
        </svg>';

        return Dashboard::new()
            ->setTitle($logo . 'Reviews')
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
