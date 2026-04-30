<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/products')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    #[Route('', name: 'app_product_index')]
    public function index(Request $request, ProductRepository $repository): Response
    {
        $qb = $repository->createIndexQueryBuilder();
        $adapter = new QueryAdapter($qb);

        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'product/index.html.twig';
        if ($request->headers->has('Turbo-Frame')) {
            $view = 'product/list.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
        ]);
    }
}
