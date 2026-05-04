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
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository             $productRepository,
        private UserRepository                $userRepository,
        private AdminContextProviderInterface $adminContextProvider,
        private AdminContextFactory           $adminContextFactory,
        private RequestStack                  $requestStack,
    ) {}

    public function index(): Response
    {
        // Recupera o AdminContext que o AdminRouterSubscriber já colocou no request
        $request = $this->requestStack->getCurrentRequest();
        $adminContext = $request?->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);

        // Se por algum motivo não existir, cria via factory (pode falhar em dev sem cache)
        if (!$adminContext instanceof AdminContext) {
            $adminContext = $this->adminContextFactory->create($request, $this, null);
        }

        // Popula o AdminContextProvider para que ea() no Twig retorne o contexto correto
        $this->adminContextProvider->setContext($adminContext);

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
        yield MenuItem::linkToCrud('Usu\u00e1rios', 'fa fa-user', User::class)
            ->setController(UserCrudController::class);
    }
}
