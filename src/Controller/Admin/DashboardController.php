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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository    $productRepository,
        private UserRepository       $userRepository,
        private AdminContextFactory  $adminContextFactory,
        private Environment          $twig,
        private RequestStack         $requestStack,
    ) {}

    public function index(): Response
    {
        // Inicializa o AdminContext manualmente (necessário para ea() funcionar no Twig)
        $request = $this->requestStack->getCurrentRequest();
        $adminContext = $this->adminContextFactory->create($request, $this, null);

        // Injeta o contexto como global do Twig (ea()) e no atributo da request
        $this->twig->addGlobal('ea', $adminContext);
        $request->attributes->set(AdminContext::class, $adminContext);

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
