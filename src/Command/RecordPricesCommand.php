<?php
// src/Command/RecordPricesCommand.php

namespace App\Command;

use App\Repository\PriceHistoryRepository;
use App\Repository\ProductRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:record-prices',
    description: 'Registra o preço atual de todos os produtos no histórico (execute via cron diariamente).'
)]
class RecordPricesCommand extends Command
{
    public function __construct(
        private ProductRepository     $productRepository,
        private PriceHistoryRepository $priceHistoryRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $products = $this->productRepository->findAll();
        $recorded = 0;

        foreach ($products as $product) {
            $this->priceHistoryRepository->recordIfChanged($product);
            $recorded++;
        }

        $io->success("Verificados {$recorded} produtos. Histórico atualizado.");
        return Command::SUCCESS;
    }
}
