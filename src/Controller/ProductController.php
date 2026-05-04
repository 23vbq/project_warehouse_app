<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        $filters = [
            'type' => $request->query->get('type'),
        ];

        $qb = $repository->createIndexQueryBuilder($filters);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'product/index.html.twig';
        if ($request->headers->get('Turbo-Frame') === 'product_list') {
            $view = 'product/list.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
            'filters' => $filters,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $product = new Product();
        $product->setCreatedAt(new \DateTimeImmutable());
        $product->setCreatedBy($this->getUser());
        $product->setIsActive(true);

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function edit(
        Product $product,
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/edit.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_product_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function delete(
        Request $request,
        Product $product,
        EntityManagerInterface $em
    ): Response
    {
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $product->setIsActive(false);

            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/delete.html.twig', [
            'product' => $product,
        ]);
    }
}
