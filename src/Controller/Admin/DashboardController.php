<?php
// src/Controller/Admin/DashboardController.php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Collection\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ProductRepository $productRepository,
        private UserRepository    $userRepository,
    ) {}

    public function index(): Response
    {
        // Deixa o EA inicializar o AdminContext (i18n, menu, assets...)
        // e depois sobrescreve apenas o template renderizado
        return parent::index();
    }

    /**
     * EA5 chama este metodo APOS montar o contexto e ANTES de renderizar.
     * Aqui injetamos as variaveis extras e definimos o template customizado.
     */
    public function configureResponseParameters(): KeyValueStore
    {
        $params = parent::configureResponseParameters();

        // Injetamos os dados do dashboard
        $params->set('product_stats',   $this->productRepository->getStats());
        $params->set('total_users',     count($this->userRepository->findAll()));
        $params->set('latest_products', $this->productRepository->findLatest(5));

        // Sobrescreve o template que o EA vai renderizar para esta pagina
        $params->set('templateName', 'admin/dashboard.html.twig');

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
