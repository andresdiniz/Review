<?php
// src/Controller/Admin/ProductCrudController.php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Service\ProductAIService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private ProductAIService $productAIService,
        private AdminUrlGenerator $adminUrlGenerator,
        private EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produto')
            ->setEntityLabelInPlural('Produtos')
            ->setPageTitle('index', 'Lista de Produtos')
            ->setPageTitle('new', 'Novo Produto')
            ->setPageTitle('edit', 'Editar Produto')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'category', 'aiVerdict'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('name', 'Nome');

        yield SlugField::new('slug')
            ->setTargetFieldName('name')
            ->hideOnIndex();

        yield TextField::new('category', 'Categoria');

        yield NumberField::new('currentPrice', 'Preço (R$)')
            ->setNumDecimals(2)
            ->setStoredAsString(false)
            ->setFormTypeOption('attr', ['step' => '0.01'])
            ->formatValue(fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'));

        yield UrlField::new('affiliateLink', 'Link Afiliado')
            ->hideOnIndex();

        yield TextField::new('imageUrl', 'URL da Imagem')
            ->hideOnIndex()
            ->setHelp('Cole a URL direta da imagem do produto (JPG, PNG, WebP).');

        // Preview da imagem na listagem
        yield TextField::new('imageUrl', 'Imagem')
            ->onlyOnIndex()
            ->formatValue(function ($value) {
                if (!$value) {
                    return '—';
                }
                return sprintf(
                    '<img src="%s" style="height:50px;width:50px;object-fit:cover;border-radius:4px;" loading="lazy" alt="produto">',
                    htmlspecialchars($value, ENT_QUOTES)
                );
            })
            ->renderAsHtml();

        yield TextareaField::new('aiVerdict', 'Veredito IA')
            ->hideOnIndex()
            ->setNumOfRows(3);

        yield TextEditorField::new('fullReviewMarkdown', 'Review Completo')
            ->hideOnIndex()
            ->setNumOfRows(10);

        yield CollectionField::new('pros', 'Prós')
            ->hideOnIndex()
            ->allowAdd()
            ->allowDelete()
            ->setEntryType(TextType::class)
            ->setEntryIsComplex(false);

        yield CollectionField::new('cons', 'Contras')
            ->hideOnIndex()
            ->allowAdd()
            ->allowDelete()
            ->setEntryType(TextType::class)
            ->setEntryIsComplex(false);

        yield TextField::new('youtubeVideoId', 'ID do Vídeo YouTube')
            ->hideOnIndex()
            ->setHelp('Apenas o ID do vídeo, ex: dQw4w9WgXcQ');

        yield TextField::new('mercadolivreId', 'ID Mercado Livre')
            ->hideOnIndex()
            ->setHelp('Ex: MLB1234567890. Usado para atualização automática de preços.');

        yield DateTimeField::new('createdAt', 'Criado em')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');

        yield DateTimeField::new('lastUpdateAt', 'Atualizado em')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Nome'))
            ->add(TextFilter::new('category', 'Categoria'))
            ->add(NumericFilter::new('currentPrice', 'Preço'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $generateFromUrl = Action::new('generateFromUrl', 'Gerar via URL do ML', 'fa fa-magic')
            ->linkToCrudAction('generateFromUrl')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-primary');

        return $actions
            ->add(Crud::PAGE_INDEX, $generateFromUrl)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function generateFromUrl(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $url = trim((string) $request->request->get('affiliate_url', ''));

            if (empty($url)) {
                $this->addFlash('danger', 'Informe uma URL válida do Mercado Livre.');
            } else {
                try {
                    $data = $this->productAIService->generateFromAffiliateLink($url);

                    $product = new Product();
                    $product->setName($data['name']);
                    $product->setSlug($data['slug']);
                    $product->setCurrentPrice((string) $data['currentPrice']);
                    $product->setAffiliateLink($data['affiliateLink']);
                    $product->setAiVerdict($data['aiVerdict']);
                    $product->setPros($data['pros']);
                    $product->setCons($data['cons']);
                    $product->setFullReviewMarkdown($data['fullReviewMarkdown']);
                    $product->setYoutubeVideoId($data['youtubeVideoId']);
                    $product->setImageUrl($data['imageUrl'] ?? null);
                    $product->setMercadolivreId($data['mercadolivreId'] ?? null);
                    $product->setCategory($data['category'] ?? null);

                    $this->entityManager->persist($product);
                    $this->entityManager->flush();

                    $this->addFlash('success', sprintf('Produto "%s" criado com sucesso!', $product->getName()));

                    return $this->redirect(
                        $this->adminUrlGenerator
                            ->setController(self::class)
                            ->setAction(Action::EDIT)
                            ->setEntityId($product->getId())
                            ->generateUrl()
                    );
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erro ao gerar produto: ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/generate_from_url.html.twig', [
            'action' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('generateFromUrl')
                ->generateUrl(),
        ]);
    }
}
