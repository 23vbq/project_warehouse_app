<?php

namespace App\Controller;

use App\Entity\Location;
use App\Form\LocationType;
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
        $location = new Location();
        $location->setCreatedBy($this->getUser());

        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($location);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Lokalizacja "%s" została utworzona.', $location->getName()));

            return $this->turboRedirectToRoute($request, 'app_location_index');
        }

        return $this->render('location/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_location_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function edit(
        Request $request,
        Location $location,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($location);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Zmiany w lokalizacji "%s" zostały zapisane.', $location->getName()));

            return $this->turboRedirectToRoute($request, 'app_location_index');
        }

        return $this->render('location/edit.html.twig', [
            'form' => $form,
            'location' => $location,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_location_delete', methods: ['POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function delete(
        Request $request,
        Location $location,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('delete'.$location->getId(), $request->request->get('_token'))) {
            $entityManager->remove($location);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Lokalizacja "%s" została usunięta.', $location->getName()));

            return $this->turboRedirectToRoute($request, 'app_location_index');
        }

        return $this->render('location/delete.html.twig', [
            'location' => $location,
        ]);
    }
}
