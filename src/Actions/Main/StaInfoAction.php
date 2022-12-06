<?php

namespace App\Actions\Main;

use App\Exceptions\AppException;
use App\Service\MainService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class StaInfoAction
{
    public function __construct(
        private MainService $mainService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();

        $response
            ->getBody()
            ->write(
                json_encode($this->mainService->getStaInfo($params))
            );

        return $response;
    }
}
