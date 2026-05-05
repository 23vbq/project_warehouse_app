<?php

namespace App\Controller;

use App\Repository\LocationRepository;
use App\Traits\TurboTrait;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/locations')]
#[IsGranted('ROLE_USER')]
class LocationController extends AbstractController
{
    use TurboTrait;

    #[Route('', name: 'app_location_index')]
    public function index(Request $request, LocationRepository $repository): Response
    {
        $filters = [
            'query' => $request->query->get('query'),
            'showInactive' => $request->query->getBoolean('showInactive'),
        ];
        $orderBy = [
            'field' => $request->query->get('sort'),
            'direction' => $request->query->get('direction', 'asc'),
        ];

        $qb = $repository->createIndexQueryBuilder($filters, [$orderBy['field'] => $orderBy['direction']]);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'location/index.html.twig';
        $turboFrameId = $request->headers->get('Turbo-Frame');
        if ('location_list' === $turboFrameId) {
            $view = 'location/list.html.twig';
        } elseif ('location_list_table' === $turboFrameId) {
            $view = 'location/_list_table.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
            'filters' => $filters,
            'orderBy' => $orderBy,
        ]);
    }

    #[Route('/new', name: 'app_location_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        throw $this->createNotFoundException('Not implemented');
    }

    #[Route('/{id}/edit', name: 'app_location_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        throw $this->createNotFoundException('Not implemented');
    }

    #[Route('/{id}/delete', name: 'app_location_delete', methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        throw $this->createNotFoundException('Not implemented');
    }
}
