<?php

namespace App\Actions\Main;

use App\Exceptions\AppException;
use App\Service\MainService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class FastInfoAction
{
    public function __construct(
        private MainService $mainService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $response
            ->getBody()
            ->write(
                json_encode($this->mainService->getFastInfo())
            );

        return $response;
    }
}
