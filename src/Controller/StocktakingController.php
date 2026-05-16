<?php

namespace App\Controller;

use App\Entity\Stocktaking;
use App\Entity\StocktakingLine;
use App\Form\StocktakingType;
use App\Repository\StocktakingRepository;
use App\Service\StocktakingService;
use App\Traits\TurboTrait;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stocktaking')]
#[IsGranted('ROLE_USER')]
class StocktakingController extends AbstractController
{
    use TurboTrait;

    #[Route('', name: 'app_stocktaking_index')]
    public function index(Request $request, StocktakingRepository $repository): Response
    {
        $filters = [
            'query' => $request->query->get('query'),
        ];
        $orderBy = [
            'field' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('direction', 'desc'),
        ];

        $qb = $repository->createIndexQueryBuilder($filters, [$orderBy['field'] => $orderBy['direction']]);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'stocktaking/index.html.twig';
        if ('stocktaking_list' === $request->headers->get('Turbo-Frame')) {
            $view = 'stocktaking/list.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
            'filters' => $filters,
            'orderBy' => $orderBy,
        ]);
    }

    #[Route('/new', name: 'app_stocktaking_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function new(Request $request, StocktakingService $service): Response
    {
        $stocktaking = new Stocktaking();
        $stocktaking->setCreatedBy($this->getUser());

        $form = $this->createForm(StocktakingType::class, $stocktaking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->create($stocktaking);

            $this->addFlash('success', 'Inwentaryzacja została utworzona.');

            return $this->turboRedirectToRoute($request, 'app_stocktaking_show', ['id' => $stocktaking->getId()]);
        }

        return $this->render('stocktaking/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stocktaking_show', requirements: ['id' => '\d+'])]
    public function show(Stocktaking $stocktaking): Response
    {
        return $this->render('stocktaking/show.html.twig', [
            'stocktaking' => $stocktaking,
        ]);
    }

    #[Route('/{id}/lines/{line}', name: 'app_stocktaking_line_save', requirements: ['id' => '\d+', 'line' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function saveLine(Request $request, Stocktaking $stocktaking, StocktakingLine $line, StocktakingService $service): Response
    {
        if ($line->getStocktaking() !== $stocktaking) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('save_line_'.$line->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $raw = $request->request->get('counted_quantity');
        $countedQuantity = ('' === $raw || null === $raw) ? null : $raw;

        try {
            $service->saveLine($line, $countedQuantity, $this->getUser());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->turboRedirectToRoute($request, 'app_stocktaking_show', ['id' => $stocktaking->getId()]);
    }

    #[Route('/{id}/complete', name: 'app_stocktaking_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function complete(Request $request, Stocktaking $stocktaking, StocktakingService $service): Response
    {
        if (!$this->isCsrfTokenValid('complete_stocktaking_'.$stocktaking->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $service->complete($stocktaking, $this->getUser());
            $this->addFlash('success', 'Inwentaryzacja została zatwierdzona.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_stocktaking_show', ['id' => $stocktaking->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_stocktaking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function cancel(Request $request, Stocktaking $stocktaking, StocktakingService $service): Response
    {
        if (!$this->isCsrfTokenValid('cancel_stocktaking_'.$stocktaking->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $service->cancel($stocktaking, $this->getUser());
            $this->addFlash('success', 'Inwentaryzacja została anulowana.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_stocktaking_show', ['id' => $stocktaking->getId()]);
    }
}
