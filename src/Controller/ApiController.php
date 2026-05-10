<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/products', name: 'app_api_product_search')]
    #[IsGranted('ROLE_USER')]
    public function productSearch(
        Request $request,
        ProductRepository $productRepository,
    ): Response
    {
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
}