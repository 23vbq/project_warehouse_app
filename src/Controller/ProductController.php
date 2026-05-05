<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Traits\TurboTrait;
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
    use TurboTrait;

    #[Route('', name: 'app_product_index')]
    public function index(Request $request, ProductRepository $repository): Response
    {
        $filters = [
            'type' => $request->query->get('type'),
            'query' => $request->query->get('query'),
            'showInactive' => $request->query->getBoolean('showInactive'),
        ];
        $orderBy = [
            'field' => $request->query->get('sort', 'name'),
            'direction' => $request->query->get('direction', 'asc'),
        ];

        $qb = $repository->createIndexQueryBuilder($filters, [$orderBy['field'] => $orderBy['direction']]);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'product/index.html.twig';
        $turboFrameId = $request->headers->get('Turbo-Frame');
        if ('product_list' === $turboFrameId) {
            $view = 'product/list.html.twig';
        } elseif ('product_list_table' === $turboFrameId) {
            $view = 'product/_list_table.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
            'filters' => $filters,
            'orderBy' => $orderBy,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_product_index');
        }

        $product = new Product();
        $product->setCreatedBy($this->getUser());

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            $this->addFlash('success', sprintf('Produkt "%s" został dodany.', $product->getName()));

            return $this->turboRedirectToRoute($request, 'app_product_index');
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
        EntityManagerInterface $em,
    ): Response {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_product_index');
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            $this->addFlash('success', sprintf('Zmiany w produkcie "%s" zostały zapisane.', $product->getName()));

            return $this->turboRedirectToRoute($request, 'app_product_index');
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
        EntityManagerInterface $em,
    ): Response {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_product_index');
        }

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $product->setInactive();

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', sprintf('Produkt "%s" został dezaktywowany.', $product->getName()));

            return $this->turboRedirectToRoute($request, 'app_product_index');
        }

        return $this->render('product/delete.html.twig', [
            'product' => $product,
        ]);
    }
}
