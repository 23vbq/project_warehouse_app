<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TestController extends AbstractController
{
    #[Route('/', name: 'app_public')]
    public function public(): Response
    {
        return $this->render('test/public.html.twig');
    }

    #[Route('/protected', name: 'app_protected')]
    #[IsGranted('ROLE_USER')]
    public function protected(): Response
    {
        return $this->render('test/protected.html.twig');
    }
}
