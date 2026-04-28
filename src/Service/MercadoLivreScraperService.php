<?php
// src/Service/MercadoLivreScraperService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

class MercadoLivreScraperService
{
    private HttpClientInterface $httpClient;

    // Códigos de país suportados pelo Mercado Livre
    private const COUNTRY_CODES = ['MLB', 'MLA', 'MLM', 'MLC', 'MLU', 'MLV', 'MPE', 'MCO'];

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Extrai dados de um produto a partir de URL do Mercado Livre.
     *
     * @throws \RuntimeException em caso de URL inválida ou erro na API
     */
    public function extractFromUrl(string $url): array
    {
        $fullItemId   = $this->extractFullItemId($url);
        $data         = $this->fetchItemData($fullItemId);
        $description  = $this->fetchItemDescription($fullItemId);
        $categoryName = $this->fetchCategoryName($data['category_id'] ?? '');

        // Melhor imagem disponível (alta resolução)
        $imageUrl = $this->extractBestImage($data['pictures'] ?? []);

        // Usa sale_price se disponível, senão price
        $price = $data['sale_price']['amount'] ?? $data['price'] ?? 0;

        return [
            'name'           => $data['title'] ?? '',
            'price'          => (float) $price,
            'currency'       => $data['currency_id'] ?? 'BRL',
            'description'    => $description,
            'category'       => $categoryName,
            'categoryId'     => $data['category_id'] ?? '',
            'condition'      => $data['condition'] ?? 'new',
            'pictures'       => $data['pictures'] ?? [],
            'url'            => $data['permalink'] ?? $url,
            'imageUrl'       => $imageUrl,
            'mercadolivreId' => $fullItemId,   // ID completo: ex. MLB1234567890
            'soldQuantity'   => $data['sold_quantity'] ?? 0,
            'attributes'     => $this->extractKeyAttributes($data['attributes'] ?? []),
        ];
    }

    /**
     * Extrai o ID completo do item (ex: MLB1234567890) da URL.
     * Suporta formatos:
     *   - https://produto.mercadolivre.com.br/MLB-1234567890-titulo
     *   - https://www.mercadolivre.com.br/.../p/MLB1234567890
     *   - https://produto.mercadolivre.com.br/MLB1234567890-titulo
     */
    public function extractFullItemId(string $url): string
    {
        $pattern = '/\b(' . implode('|', self::COUNTRY_CODES) . ')-?(\d+)\b/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1] . $matches[2]; // ex: MLB1234567890
        }

        throw new \RuntimeException(
            sprintf('URL inválida — não foi possível identificar o ID do produto. URL: %s', $url)
        );
    }

    private function fetchItemData(string $itemId): array
    {
        try {
            $response   = $this->httpClient->request('GET', "https://api.mercadolibre.com/items/{$itemId}");
            $statusCode = $response->getStatusCode();

            if ($statusCode === 404) {
                throw new \RuntimeException("Produto {$itemId} não encontrado no Mercado Livre.");
            }
            if ($statusCode !== 200) {
                throw new \RuntimeException("API do Mercado Livre retornou status {$statusCode} para o produto {$itemId}.");
            }

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Erro de conexão com a API do Mercado Livre: ' . $e->getMessage(), 0, $e);
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException("Erro ao buscar produto {$itemId}: " . $e->getMessage(), 0, $e);
        }
    }

    private function fetchItemDescription(string $itemId): string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.mercadolibre.com/items/{$itemId}/description");

            if ($response->getStatusCode() !== 200) {
                return '';
            }

            $data = $response->toArray();
            return $data['plain_text'] ?? '';
        } catch (\Throwable) {
            // Descrição é opcional; não falha o fluxo principal
            return '';
        }
    }

    private function fetchCategoryName(string $categoryId): string
    {
        if (empty($categoryId)) {
            return '';
        }

        try {
            $response = $this->httpClient->request('GET', "https://api.mercadolibre.com/categories/{$categoryId}");

            if ($response->getStatusCode() !== 200) {
                return $categoryId;
            }

            $data        = $response->toArray();
            // Usa o nome da categoria mais específica no caminho
            $pathFromRoot = $data['path_from_root'] ?? [];
            if (!empty($pathFromRoot)) {
                return end($pathFromRoot)['name'] ?? $data['name'] ?? $categoryId;
            }

            return $data['name'] ?? $categoryId;
        } catch (\Throwable) {
            return $categoryId;
        }
    }

    private function extractBestImage(array $pictures): ?string
    {
        if (empty($pictures)) {
            return null;
        }

        foreach ($pictures as $picture) {
            if (isset($picture['url'])) {
                // Substitui -I (thumbnail) por -O (imagem original/alta res) no padrão ML
                return str_replace('-I.', '-O.', $picture['url']);
            }
        }

        return null;
    }

    /**
     * Extrai atributos relevantes (marca, modelo, cor, etc.)
     */
    private function extractKeyAttributes(array $attributes): array
    {
        $relevant = ['BRAND', 'MODEL', 'COLOR', 'WEIGHT', 'WIDTH', 'HEIGHT', 'DEPTH'];
        $result   = [];

        foreach ($attributes as $attr) {
            $id = strtoupper($attr['id'] ?? '');
            if (in_array($id, $relevant, true) && !empty($attr['value_name'])) {
                $result[$attr['name'] ?? $id] = $attr['value_name'];
            }
        }

        return $result;
    }
}
