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
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository          $productRepository,
        private UserRepository             $userRepository,
        private AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {}

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'product_stats'   => $this->productRepository->getStats(),
            'total_users'     => count($this->userRepository->findAll()),
            'latest_products' => $this->productRepository->findLatest(5),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/img/logo.png" alt="Logo" style="height:30px"> Reviews')
            ->setFaviconPath('favicon.ico');
    }

    public function configureMenuItems(): iterable
    {
        $productsUrl = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->generateUrl();

        $usersUrl = $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->generateUrl();

        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToUrl('Produtos', 'fa fa-tag', $productsUrl);
        yield MenuItem::linkToUrl('Usuários', 'fa fa-user', $usersUrl);
    }
}
