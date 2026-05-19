<?php

namespace App\Controller;

use App\Entity\Correction;
use App\Entity\Operation;
use App\Entity\OperationLine;
use App\Entity\Receipt;
use App\Entity\Release;
use App\Entity\Relocation;
use App\Form\CorrectionType;
use App\Form\ReceiptType;
use App\Form\ReleaseType;
use App\Form\RelocationType;
use App\Repository\CorrectionRepository;
use App\Repository\OperationRepository;
use App\Service\CorrectionService;
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
    public function show(Operation $operation, CorrectionRepository $correctionRepository): Response
    {
        $corrections = $correctionRepository->findBy(
            ['correctedOperation' => $operation],
            ['createdAt' => 'DESC']
        );

        return $this->render('operation/show.html.twig', [
            'operation' => $operation,
            'corrections' => $corrections,
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
            Operation::TYPE_CORRECTION => 'operation/_partials/correction_show_lines_table.html.twig',
            default => throw new \InvalidArgumentException('Invalid document type: '.$documentType),
        };

        return $this->render('layout/_partials/turbo_frame_wrapper.html.twig', [
            'turboFrameId' => 'operation-lines-details-'.$operation->getId(),
            'content' => $this->renderView($view, ['operation' => $operation]),
        ]);
    }

    #[Route('/{id}/print', name: 'app_operation_print', requirements: ['id' => '\d+'])]
    public function print(Operation $operation): Response
    {
        if (!$operation->isConfirmed()) {
            $this->addFlash('error', 'Można drukować tylko zatwierdzone operacje.');

            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $view = match ($operation->getDocumentType()) {
            Operation::TYPE_RECEIPT => 'operation/print/receipt.html.twig',
            Operation::TYPE_RELEASE => 'operation/print/release.html.twig',
            Operation::TYPE_RELOCATION => 'operation/print/relocation.html.twig',
            Operation::TYPE_ADJUSTMENT => 'operation/print/adjustment.html.twig',
            Operation::TYPE_CORRECTION => 'operation/print/correction.html.twig',
            default => throw new \InvalidArgumentException('Invalid document type: '.$operation->getDocumentType()),
        };

        return $this->render($view, [
            'operation' => $operation,
        ]);
    }

    #[Route('/{id}/confirm', name: 'app_operation_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function confirm(Request $request, Operation $operation, OperationService $operationService): Response
    {
        if (!$this->isCsrfTokenValid('confirm_operation_'.$operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Nieprawidłowy token CSRF.');

            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        try {
            $operationService->confirm($operation, $this->getUser());
            $this->addFlash('success', sprintf('Operacja %s została zatwierdzona.', $operation->getFullNumber()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

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

        return $this->render('operation/receipt_form.html.twig', [
            'form' => $form,
            'receipt' => $receipt,
            'pageTitle' => 'Nowe przyjęcie (PZ)',
            'formAction' => $this->generateUrl('app_operation_new_receipt'),
            'cancelUrl' => $this->generateUrl('app_operation_index'),
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

        return $this->render('operation/release_form.html.twig', [
            'form' => $form,
            'release' => $release,
            'pageTitle' => 'Nowe wydanie (WZ)',
            'formAction' => $this->generateUrl('app_operation_new_release'),
            'cancelUrl' => $this->generateUrl('app_operation_index'),
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

        return $this->render('operation/relocation_form.html.twig', [
            'form' => $form,
            'relocation' => $relocation,
            'pageTitle' => 'Nowe przesunięcie (MM)',
            'formAction' => $this->generateUrl('app_operation_new_relocation'),
            'cancelUrl' => $this->generateUrl('app_operation_index'),
        ]);
    }

    #[Route('/new/correction/{id}', name: 'app_operation_new_correction', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function newCorrection(
        Request $request,
        Operation $correctedOperation,
        OperationService $operationService,
        CorrectionService $correctionService,
        EntityManagerInterface $em,
    ): Response {
        $correctableTypes = [Operation::TYPE_RECEIPT, Operation::TYPE_RELEASE, Operation::TYPE_RELOCATION, Operation::TYPE_ADJUSTMENT];

        if (!$correctedOperation->isConfirmed() || !in_array($correctedOperation->getDocumentType(), $correctableTypes, true)) {
            $this->addFlash('error', 'Można korygować tylko potwierdzone dokumenty PZ, WZ, MM i INW.');

            return $this->redirectToRoute('app_operation_show', ['id' => $correctedOperation->getId()]);
        }

        $correction = new Correction();
        $correction->setCreatedBy($this->getUser());
        $correction->setCorrectedOperation($correctedOperation);

        foreach ($correctedOperation->getOperationLines() as $originalLine) {
            $line = new OperationLine();
            $line->setProduct($originalLine->getProduct());
            $line->setQuantity($originalLine->getQuantity());

            if ($correctedOperation instanceof Receipt) {
                $line->setLocationFrom($originalLine->getLocationTo());
            } elseif ($correctedOperation instanceof Release) {
                $line->setLocationTo($originalLine->getLocationFrom());
            } elseif ($correctedOperation instanceof Relocation) {
                $line->setLocationFrom($originalLine->getLocationTo());
                $line->setLocationTo($originalLine->getLocationFrom());
            } elseif ($correctedOperation instanceof Adjustment) {
                if (null !== $originalLine->getLocationTo()) {
                    $line->setLocationFrom($originalLine->getLocationTo());
                } else {
                    $line->setLocationTo($originalLine->getLocationFrom());
                }
            }

            $correction->addOperationLine($line);
        }

        $form = $this->createForm(CorrectionType::class, $correction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $desiredLines = array_values($correction->getOperationLines()->toArray());
            $computed = $correctionService->computeLines($desiredLines, $correctedOperation);

            if (empty($computed)) {
                $this->addFlash('error', 'Korekta nie zawiera żadnych zmian względem oryginału.');
            } else {
                $correctionService->buildLines($correction, $correctedOperation);
                $operationService->generateNumber($correction);

                $em->persist($correction);
                $em->flush();

                $this->addFlash('success', sprintf('Korekta %s została utworzona.', $correction->getFullNumber()));

                return $this->redirectToRoute('app_operation_show', ['id' => $correction->getId()]);
            }
        }

        return $this->render('operation/correction_form.html.twig', [
            'form' => $form,
            'correction' => $correction,
            'correctedOperation' => $correctedOperation,
            'pageTitle' => sprintf('Korekta do %s', $correctedOperation->getFullNumber()),
            'formAction' => $this->generateUrl('app_operation_new_correction', ['id' => $correctedOperation->getId()]),
            'cancelUrl' => $this->generateUrl('app_operation_show', ['id' => $correctedOperation->getId()]),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_operation_edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function edit(Request $request, Operation $operation, EntityManagerInterface $em): Response
    {
        if ($operation->isConfirmed()) {
            $this->addFlash('error', 'Nie można edytować zatwierdzonej operacji.');

            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        if ($operation instanceof Correction) {
            $this->addFlash('error', 'Korekty nie można edytować. Usuń ją i utwórz nową.');

            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        if ($operation instanceof Receipt) {
            $form = $this->createForm(ReceiptType::class, $operation);
            $template = 'operation/receipt_form.html.twig';
            $pageTitle = 'Edytuj przyjęcie (PZ)';
            $operationVariable = 'receipt';
        } elseif ($operation instanceof Release) {
            $form = $this->createForm(ReleaseType::class, $operation);
            $template = 'operation/release_form.html.twig';
            $pageTitle = 'Edytuj wydanie (WZ)';
            $operationVariable = 'release';
        } else {
            $form = $this->createForm(RelocationType::class, $operation);
            $template = 'operation/relocation_form.html.twig';
            $pageTitle = 'Edytuj przesunięcie (MM)';
            $operationVariable = 'relocation';
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('Operacja %s została zaktualizowana.', $operation->getFullNumber()));

            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        return $this->render($template, [
            'form' => $form,
            $operationVariable => $operation,
            'pageTitle' => $pageTitle,
            'formAction' => $this->generateUrl('app_operation_edit', ['id' => $operation->getId()]),
            'cancelUrl' => $this->generateUrl('app_operation_show', ['id' => $operation->getId()]),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_operation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_WAREHOUSE_EMPLOYEE')]
    public function delete(Request $request, Operation $operation, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_operation_'.$operation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Nieprawidłowy token CSRF.');

            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        if ($operation->isConfirmed()) {
            $this->addFlash('error', 'Nie można usunąć zatwierdzonej operacji.');

            return $this->redirectToRoute('app_operation_show', ['id' => $operation->getId()]);
        }

        $fullNumber = $operation->getFullNumber();

        $em->remove($operation);
        $em->flush();

        $this->addFlash('success', sprintf('Operacja %s została usunięta.', $fullNumber));

        return $this->redirectToRoute('app_operation_index');
    }
}
