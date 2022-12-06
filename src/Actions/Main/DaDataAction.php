<?php

namespace App\Actions\Main;

use App\Exceptions\AppException;
use App\Service\DaDataService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class DaDataAction
{
    public function __construct(
        private DaDataService $daDataService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();

        $response
            ->getBody()
            ->write(
                json_encode($this->daDataService->search($params))
            );

        return $response;
    }
}
