<?php

namespace App\Traits;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait TurboTrait
{
    protected function turboRedirectToRoute(Request $request, string $route, array $parameters = [], int $status = 302): Response
    {
        $url = $this->generateUrl($route, $parameters);

        if ($request->headers->has('Turbo-Frame')) {
            return new Response(null, 200, ['X-Turbo-Redirect' => $url]);
        }

        return $this->redirect($url, $status);
    }
}
