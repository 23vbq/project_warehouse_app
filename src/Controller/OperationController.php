<?php

namespace App\Controller;

use App\Entity\Operation;
use App\Entity\OperationLine;
use App\Entity\Receipt;
use App\Entity\Release;
use App\Entity\Relocation;
use App\Form\ReceiptType;
use App\Form\ReleaseType;
use App\Form\RelocationType;
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
            'type' => $request->query->get('type'),
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

        $view = 'operation/index.html.twig';
        $turboFrameId = $request->headers->get('Turbo-Frame');
        if ('operation_list' === $turboFrameId) {
            $view = 'operation/list.html.twig';
        } elseif ('operation_list_table' === $turboFrameId) {
            $view = 'operation/_list_table.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
            'filters' => $filters,
            'orderBy' => $orderBy,
        ]);
    }

    #[Route('/{id}', name: 'app_operation_show', requirements: ['id' => '\d+'])]
    public function show(Operation $operation): Response
    {
        return $this->render('operation/show.html.twig', [
            'operation' => $operation,
        ]);
    }

    #[Route('/{id}/lines', name: 'app_operation_lines_details', requirements: ['id' => '\d+'])]
    public function linesDetails(Request $request, Operation $operation): Response
    {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $documentType = $operation->getDocumentType();

        $view = match ($documentType) {
            Operation::TYPE_RECEIPT => 'operation/_partials/receipt_show_lines_table.html.twig',
            Operation::TYPE_RELEASE => 'operation/_partials/release_show_lines_table.html.twig',
            Operation::TYPE_RELOCATION => 'operation/_partials/relocation_show_lines_table.html.twig',
            default => throw new \InvalidArgumentException('Invalid document type: '.$documentType),
        };

        return $this->render('layout/_partials/turbo_frame_wrapper.html.twig', [
            'turboFrameId' => 'operation-lines-details-'.$operation->getId(),
            'content' => $this->renderView($view, ['operation' => $operation]),
        ]);
    }

    #[Route('/{id}/confirm', name: 'app_operation_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function confirm(Operation $operation): Response
    {
        // TODO: implement confirmation logic
        return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
    }

    #[Route('/new/receipt', name: 'app_operation_new_receipt')]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function newReceipt(
        Request $request,
        OperationService $operationService,
        EntityManagerInterface $em,
    ): Response {
        $receipt = new Receipt();
        $receipt->setCreatedBy($this->getUser());

        $form = $this->createForm(ReceiptType::class, $receipt);
        $form->handleRequest($request);

        if (!$form->isSubmitted() && $receipt->getOperationLines()->isEmpty()) {
            $receipt->addOperationLine(new OperationLine());
            $form = $this->createForm(ReceiptType::class, $receipt);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $operationService->generateNumber($receipt);

            $em->persist($receipt);
            $em->flush();

            $this->addFlash('success', sprintf('Przyjęcie %s zostało utworzone.', $receipt->getFullNumber()));

            return $this->redirectToRoute('app_operation_show', ['id' => $receipt->getId()]);
        }

        return $this->render('operation/receipt_new.html.twig', [
            'form' => $form,
            'receipt' => $receipt,
        ]);
    }

    #[Route('/new/release', name: 'app_operation_new_release')]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function newRelease(
        Request $request,
        OperationService $operationService,
        EntityManagerInterface $em,
    ): Response {
        $release = new Release();
        $release->setCreatedBy($this->getUser());

        $form = $this->createForm(ReleaseType::class, $release);
        $form->handleRequest($request);

        if (!$form->isSubmitted() && $release->getOperationLines()->isEmpty()) {
            $release->addOperationLine(new OperationLine());
            $form = $this->createForm(ReleaseType::class, $release);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $operationService->generateNumber($release);

            $em->persist($release);
            $em->flush();

            $this->addFlash('success', sprintf('Wydanie %s zostało utworzone.', $release->getFullNumber()));

            return $this->redirectToRoute('app_operation_show', ['id' => $release->getId()]);
        }

        return $this->render('operation/release_new.html.twig', [
            'form' => $form,
            'release' => $release,
        ]);
    }

    #[Route('/new/relocation', name: 'app_operation_new_relocation')]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function newRelocation(
        Request $request,
        OperationService $operationService,
        EntityManagerInterface $em,
    ): Response {
        $relocation = new Relocation();
        $relocation->setCreatedBy($this->getUser());

        $form = $this->createForm(RelocationType::class, $relocation);
        $form->handleRequest($request);

        if (!$form->isSubmitted() && $relocation->getOperationLines()->isEmpty()) {
            $relocation->addOperationLine(new OperationLine());
            $form = $this->createForm(RelocationType::class, $relocation);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $operationService->generateNumber($relocation);

            $em->persist($relocation);
            $em->flush();

            $this->addFlash('success', sprintf('Przesunięcie %s zostało utworzone.', $relocation->getFullNumber()));

            return $this->redirectToRoute('app_operation_show', ['id' => $relocation->getId()]);
        }

        return $this->render('operation/relocation_new.html.twig', [
            'form' => $form,
            'relocation' => $relocation,
        ]);
    }
}
