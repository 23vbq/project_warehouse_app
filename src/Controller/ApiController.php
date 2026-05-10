<?php

namespace App\Controller;

use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/product/search', name: 'app_api_product_search')]
    #[IsGranted('ROLE_USER')]
    public function productSearch(
        Request $request,
        ProductRepository $productRepository,
    ): Response {
        $query = $request->query->get('query', '');
        $limit = 10;

        $products = $productRepository->searchByQuery($query, $limit);

        return $this->json(array_map(fn ($product) => [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'ean' => $product->getEan(),
        ], $products));
    }

    #[Route('/product/get', name: 'app_api_product_get')]
    #[IsGranted('ROLE_USER')]
    public function productGet(
        Request $request,
        ProductRepository $productRepository,
    ): Response {
        $id = $request->query->getInt('id');
        $product = $productRepository->find($id);

        if (!$product) {
            return $this->json(['error' => 'Produkt nie znaleziony'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'ean' => $product->getEan(),
            'unitPrice' => $product->getUnitPrice(),
        ]);
    }

    #[Route('/location/search', name: 'app_api_location_search')]
    #[IsGranted('ROLE_USER')]
    public function locationSearch(
        Request $request,
        LocationRepository $locationRepository,
    ): Response {
        $query = $request->query->get('query', '');
        $limit = 10;

        $locations = $locationRepository->searchByQuery($query, $limit);

        return $this->json(array_map(fn ($location) => [
            'id' => $location->getId(),
            'code' => $location->getCode(),
            'name' => $location->getName(),
        ], $locations));
    }
}
