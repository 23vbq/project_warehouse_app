<?php

namespace App\Pagerfanta;

use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;
use Pagerfanta\RouteGenerator\RouteGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestRouteGeneratorFactory implements RouteGeneratorFactoryInterface
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function create(array $options = []): RouteGeneratorInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        $path = $request->getPathInfo();
        $params = $request->query->all();

        return new class($path, $params) implements RouteGeneratorInterface {
            public function __construct(
                private readonly string $path,
                private readonly array $params,
            ) {}

            public function __invoke(int $page): string
            {
                $params = array_merge($this->params, ['page' => $page]);
                if ($page === 1) {
                    unset($params['page']);
                }
                return $this->path . ($params ? '?' . http_build_query($params) : '');
            }
        };
    }
}
