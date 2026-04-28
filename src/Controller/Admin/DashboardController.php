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
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Registry\DashboardControllerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository $productRepository,
        private UserRepository $userRepository,
        private AdminContextFactory $adminContextFactory,
        private RequestStack $requestStack,
        private DashboardControllerRegistry $dashboardControllerRegistry
    ) {}

    public function index(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $context = $this->adminContextFactory->create($request, $this, null);
        $request->attributes->set(AdminContext::class, $context);

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
