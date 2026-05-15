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
        $qb = $repository->createIndexQueryBuilder();

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'stocktaking/index.html.twig';
        if ('stocktaking_list' === $request->headers->get('Turbo-Frame')) {
            $view = 'stocktaking/list.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
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
        throw new \Exception('Not implemented');
    }

    #[Route('/{id}/lines/{line}', name: 'app_stocktaking_line_save', requirements: ['id' => '\d+', 'line' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function saveLine(Stocktaking $stocktaking, StocktakingLine $line): Response
    {
        throw new \Exception('Not implemented');
    }

    #[Route('/{id}/complete', name: 'app_stocktaking_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function complete(Request $request, Stocktaking $stocktaking): Response
    {
        throw new \Exception('Not implemented');
    }

    #[Route('/{id}/cancel', name: 'app_stocktaking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function cancel(Request $request, Stocktaking $stocktaking): Response
    {
        throw new \Exception('Not implemented');
    }
}
