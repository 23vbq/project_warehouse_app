<?php

namespace App\Controller;

use App\Entity\OperationLine;
use App\Entity\Receipt;
use App\Form\ReceiptType;
use App\Repository\OperationRepository;
use App\Service\OperationService;
use App\Traits\TurboTrait;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/operations')]
#[IsGranted('ROLE_USER')]
class OperationController extends AbstractController
{
    use TurboTrait;

    #[Route('', name: 'app_operation_index')]
    public function index(Request $request, OperationRepository $repository): Response
    {
        $filters = [
            'type'  => $request->query->get('type'),
            'query' => $request->query->get('query'),
        ];
        $orderBy = [
            'field'     => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('direction', 'desc'),
        ];

        $qb = $repository->createIndexQueryBuilder($filters, [$orderBy['field'] => $orderBy['direction']]);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'operation/index.html.twig';
        $turboFrameId = $request->headers->get('Turbo-Frame');
        if ('operation_list' === $turboFrameId) {
            $view = 'operation/list.html.twig';
        } elseif ('operation_list_table' === $turboFrameId) {
            $view = 'operation/_list_table.html.twig';
        }

        return $this->render($view, [
            'pager'   => $pager,
            'filters' => $filters,
            'orderBy' => $orderBy,
        ]);
    }

    #[Route('/new/receipt', name: 'app_operation_new_receipt')]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function newReceipt(
        Request $request,
        OperationService $operationService,
        EntityManagerInterface $em,
    ): Response {
        $receipt = new Receipt();
        $receipt->setCreatedBy($this->getUser());
        $receipt->addOperationLine(new OperationLine());

        $form = $this->createForm(ReceiptType::class, $receipt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $operationService->generateNumber($receipt);

            $em->persist($receipt);
            $em->flush();

            $this->addFlash('success', sprintf('Przyjęcie %s zostało utworzone.', $receipt->getFullNumber()));

            return $this->redirectToRoute('app_operation_index');
        }

        return $this->render('operation/receipt_new.html.twig', [
            'form'    => $form,
            'receipt' => $receipt,
        ]);
    }
}
