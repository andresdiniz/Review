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
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
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
            ->setPaginatorPageSize(20)
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig']);
    }

    public function configureFields(string $pageName): iterable
    {
        // ── Somente na listagem ──────────────────────────────────────────────
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        // Preview de imagem na listagem
        yield TextField::new('imageUrl', 'Imagem')
            ->onlyOnIndex()
            ->formatValue(function ($value) {
                if (!$value) {
                    return '<span class="text-muted">—</span>';
                }
                return sprintf(
                    '<img src="%s" style="height:48px;width:48px;object-fit:cover;border-radius:6px;" loading="lazy" alt="produto">',
                    htmlspecialchars($value, ENT_QUOTES)
                );
            })
            ->renderAsHtml();

        // ── Em todas as páginas ──────────────────────────────────────────────
        yield TextField::new('name', 'Nome do Produto');

        yield TextField::new('category', 'Categoria');

        yield NumberField::new('currentPrice', 'Preço (R$)')
            ->setNumDecimals(2)
            ->setStoredAsString(false)
            ->setFormTypeOption('attr', ['step' => '0.01', 'min' => '0'])
            ->formatValue(fn($v) => $v !== null ? 'R$ ' . number_format((float) $v, 2, ',', '.') : '—');

        yield NumberField::new('score', 'Nota IA (0–10)')
            ->setNumDecimals(1)
            ->setStoredAsString(true)
            ->setHelp('Nota gerada pela IA de 0.0 a 10.0')
            ->setFormTypeOption('attr', ['step' => '0.1', 'min' => '0', 'max' => '10']);

        // ── Somente nos formulários (new / edit) ─────────────────────────────
        yield SlugField::new('slug', 'Slug (URL)')
            ->setTargetFieldName('name')
            ->hideOnIndex()
            ->setHelp('Gerado automaticamente a partir do nome. Edite somente se necessário.');

        yield UrlField::new('affiliateLink', 'Link Afiliado')
            ->hideOnIndex()
            ->setHelp('URL completa do produto (link de afiliado ou direto do ML)');

        yield TextField::new('imageUrl', 'URL da Imagem')
            ->hideOnIndex()
            ->setHelp('Cole a URL direta da imagem do produto (JPG, PNG, WebP). Ex: https://...');

        yield TextField::new('mercadolivreId', 'ID Mercado Livre')
            ->hideOnIndex()
            ->setHelp('Ex: MLB1234567890 — usado para atualização automática de preços.');

        yield TextField::new('youtubeVideoId', 'ID do Vídeo YouTube')
            ->hideOnIndex()
            ->setHelp('Apenas o ID do vídeo. Ex: dQw4w9WgXcQ');

        yield TextareaField::new('aiVerdict', 'Veredito IA')
            ->hideOnIndex()
            ->setNumOfRows(3)
            ->setHelp('Resumo gerado pela IA sobre o produto.');

        yield CollectionField::new('pros', 'Prós')
            ->hideOnIndex()
            ->allowAdd()
            ->allowDelete()
            ->setEntryType(TextType::class)
            ->setEntryIsComplex(false)
            ->setHelp('Adicione os pontos positivos do produto.');

        yield CollectionField::new('cons', 'Contras')
            ->hideOnIndex()
            ->allowAdd()
            ->allowDelete()
            ->setEntryType(TextType::class)
            ->setEntryIsComplex(false)
            ->setHelp('Adicione os pontos negativos do produto.');

        yield TextEditorField::new('fullReviewMarkdown', 'Review Completo (Markdown)')
            ->hideOnIndex()
            ->setNumOfRows(15)
            ->setHelp('Conteúdo completo do review em Markdown. Mínimo recomendado: 300 palavras.');

        // ── Somente leitura (detalhes / listagem) ────────────────────────────
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
            ->add(NumericFilter::new('currentPrice', 'Preço'))
            ->add(NumericFilter::new('score', 'Nota IA'));
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
            // Valida CSRF token
            if (!$this->isCsrfTokenValid('ea-generate-from-url', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de segurança inválido. Tente novamente.');

                return $this->render('admin/generate_from_url.html.twig', [
                    'action' => $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction('generateFromUrl')
                        ->generateUrl(),
                ]);
            }

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
                    $product->setYoutubeVideoId($data['youtubeVideoId'] ?? null);
                    $product->setImageUrl($data['imageUrl'] ?? null);
                    $product->setMercadolivreId($data['mercadolivreId'] ?? null);
                    $product->setCategory($data['category'] ?? null);
                    $product->setScore(isset($data['score']) ? (string) $data['score'] : null);

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
