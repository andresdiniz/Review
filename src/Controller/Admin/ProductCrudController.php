<?php
// src/Controller/Admin/ProductCrudController.php

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produto')
            ->setEntityLabelInPlural('Produtos')
            ->setSearchFields(['name', 'category', 'aiVerdict'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    /**
     * Carrega EasyMDE (CDN) + nosso init/CSS local apenas nas páginas de
     * novo/editar produto (onde o campo fullReviewMarkdown aparece).
     */
    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            // EasyMDE — editor Markdown rico
            ->addCssFile('https://unpkg.com/easymde/dist/easymde.min.css')
            ->addJsFile('https://unpkg.com/easymde/dist/easymde.min.js')
            // Estilos e inicialização locais
            ->addCssFile('/admin/easymde-init.css')
            ->addJsFile('/admin/easymde-init.js');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('category', 'Categoria'))
            ->add(NumericFilter::new('score', 'Nota'))
            ->add(NumericFilter::new('currentPrice', 'Preço'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'Nome');
        yield TextField::new('slug', 'Slug')->onlyOnDetail();
        yield TextField::new('category', 'Categoria');
        yield MoneyField::new('currentPrice', 'Preço')->setCurrency('BRL');
        yield NumberField::new('score', 'Nota (0-10)')
            ->setNumDecimals(1)
            ->setHelp('0 a 10');
        yield UrlField::new('affiliateLink', 'Link Afiliado');
        yield TextField::new('imageUrl', 'URL Imagem');
        yield TextField::new('mercadolivreId', 'ID Mercado Livre');
        yield TextField::new('youtubeVideoId', 'ID YouTube');

        yield TextareaField::new('aiVerdict', 'Veredito IA')
            ->hideOnIndex()
            ->setNumOfRows(4);

        // Campo com editor Markdown rico (EasyMDE)
        yield TextareaField::new('fullReviewMarkdown', 'Review Completo (Markdown)')
            ->hideOnIndex()
            ->setNumOfRows(20)
            ->setHelp('Use Markdown para formatar o review. O editor suporta ✅ toolbar, 🔍 preview ao vivo, ↔ lado a lado e ▢ tela cheia.')
            ->setFormTypeOption('attr', [
                'data-easymde' => 'true',
                'class'        => 'easymde-field',
            ]);

        yield DateTimeField::new('createdAt', 'Criado em')->onlyOnIndex();
        yield DateTimeField::new('lastUpdateAt', 'Atualizado em')->onlyOnIndex();
    }
}
