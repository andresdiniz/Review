<?php
// src/Command/UpdateProductPricesCommand.php

namespace App\Command;

use App\Repository\ProductRepository;
use App\Service\MercadoLivreScraperService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'app:update-prices',
    description: 'Atualiza os preços dos produtos via API do Mercado Livre.'
)]
class UpdateProductPricesCommand extends Command
{
    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private MercadoLivreScraperService $scraperService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Simula a atualização sem salvar no banco de dados.')
            ->addOption('product-id', 'p', InputOption::VALUE_OPTIONAL,
                'Atualiza somente o produto com este ID interno.')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL,
                'Número de produtos a processar antes de limpar o EntityManager (evita memory leak).', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $dryRun    = (bool) $input->getOption('dry-run');
        $productId = $input->getOption('product-id');
        $batchSize = (int) $input->getOption('batch-size');

        if ($dryRun) {
            $io->note('Modo DRY-RUN ativo: nenhuma alteração será salva.');
        }

        // Busca produtos: todos ou apenas o especificado
        if ($productId) {
            $product = $this->productRepository->find($productId);
            if (!$product) {
                $io->error("Produto com ID {$productId} não encontrado.");
                return Command::FAILURE;
            }
            $products = [$product];
        } else {
            $products = $this->productRepository->findAll();
        }

        $total = count($products);
        $io->title(sprintf('Atualizando preços de %d produto(s)...', $total));

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $progressBar->start();

        $updated  = 0;
        $skipped  = 0;
        $errors   = 0;
        $changes  = [];

        foreach ($products as $i => $product) {
            $progressBar->setMessage(mb_strimwidth($product->getName(), 0, 40, '…'));
            $progressBar->advance();

            // Resolve o ID do ML
            $mlId = $product->getMercadolivreId();

            if (!$mlId) {
                // Tenta extrair da URL do link afiliado
                $affiliateLink = $product->getAffiliateLink();
                if (!$affiliateLink) {
                    $io->writeln('');
                    $io->warning(sprintf('[IGNORADO] "%s" — sem ID e sem link afiliado.', $product->getName()));
                    $skipped++;
                    continue;
                }

                try {
                    $finalUrl = $this->followRedirects($affiliateLink);
                    $mlId     = $this->scraperService->extractFullItemId($finalUrl);

                    // Persiste o ID encontrado para não ter que resolver novamente
                    if (!$dryRun) {
                        $product->setMercadolivreId($mlId);
                    }
                } catch (\Exception $e) {
                    $io->writeln('');
                    $io->error(sprintf('[ERRO] "%s" — %s', $product->getName(), $e->getMessage()));
                    $errors++;
                    continue;
                }
            }

            try {
                $response   = $this->httpClient->request('GET', "https://api.mercadolibre.com/items/{$mlId}");
                $statusCode = $response->getStatusCode();

                if ($statusCode === 404) {
                    $io->writeln('');
                    $io->warning(sprintf('[404] "%s" (ID: %s) — produto não encontrado na API.', $product->getName(), $mlId));
                    $errors++;
                    continue;
                }

                if ($statusCode !== 200) {
                    throw new \RuntimeException("HTTP {$statusCode}");
                }

                $data     = $response->toArray();
                $newPrice = $data['sale_price']['amount'] ?? $data['price'] ?? null;

                if ($newPrice === null) {
                    $io->writeln('');
                    $io->warning(sprintf('[SEM PREÇO] "%s" — preço não encontrado na resposta.', $product->getName()));
                    $errors++;
                    continue;
                }

                $newPrice = (float) $newPrice;
                $oldPrice = (float) $product->getCurrentPrice();

                if (abs($newPrice - $oldPrice) > 0.001) {
                    $changes[] = [
                        'name'      => $product->getName(),
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'diff'      => $newPrice - $oldPrice,
                    ];

                    if (!$dryRun) {
                        $product->setCurrentPrice((string) $newPrice);
                        $product->setLastUpdateAt(new \DateTimeImmutable());
                    }

                    $updated++;
                } else {
                    $skipped++; // Preço igual, não precisa atualizar
                }

                // Limpa o EntityManager a cada $batchSize produtos para evitar memory leak
                if (!$dryRun && ($i + 1) % $batchSize === 0) {
                    $this->em->flush();
                    $this->em->clear();
                    // Re-busca produtos restantes após clear() para não perder referências
                }
            } catch (TransportExceptionInterface $e) {
                $io->writeln('');
                $io->error(sprintf('[CONEXÃO] "%s" — %s', $product->getName(), $e->getMessage()));
                $errors++;
            } catch (\Exception $e) {
                $io->writeln('');
                $io->error(sprintf('[ERRO] "%s" — %s', $product->getName(), $e->getMessage()));
                $errors++;
            }
        }

        // Flush final
        if (!$dryRun) {
            $this->em->flush();
        }

        $progressBar->finish();
        $output->writeln('');

        // Tabela de alterações de preço
        if (!empty($changes)) {
            $io->section('Alterações de Preço');
            $io->table(
                ['Produto', 'Preço Anterior', 'Novo Preço', 'Diferença'],
                array_map(fn($c) => [
                    mb_strimwidth($c['name'], 0, 50, '…'),
                    'R$ ' . number_format($c['old_price'], 2, ',', '.'),
                    'R$ ' . number_format($c['new_price'], 2, ',', '.'),
                    ($c['diff'] > 0 ? '▲ +' : '▼ ') . 'R$ ' . number_format(abs($c['diff']), 2, ',', '.'),
                ], $changes)
            );
        }

        // Resumo final
        $io->success(sprintf(
            'Concluído! Atualizados: %d | Sem alteração: %d | Erros: %d',
            $updated,
            $skipped,
            $errors
        ));

        if ($dryRun) {
            $io->note('Nenhuma alteração foi salva (dry-run). Execute sem --dry-run para persistir.');
        }

        return $errors > 0 && $updated === 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Segue redirecionamentos HTTP e retorna a URL final.
     */
    private function followRedirects(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'max_redirects' => 5,
            'headers'       => [
                'User-Agent'      => 'Mozilla/5.0 (compatible; ReviewBot/1.0)',
                'Accept'          => 'text/html,application/xhtml+xml,*/*',
                'Accept-Language' => 'pt-BR,pt;q=0.9',
            ],
        ]);

        $finalUrl = $response->getInfo('url');

        // Detecta redirecionamento para página social (não é link de produto)
        if (str_contains((string) $finalUrl, '/social/') || str_contains((string) $finalUrl, '/profile')) {
            throw new \RuntimeException("URL {$url} redirecionou para um perfil social, não é um produto.");
        }

        return (string) $finalUrl;
    }
}
