<?php

namespace App\Actions\Find;

use App\Exceptions\AppException;
use App\Service\FindService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class FindWbAction
{
    public function __construct(
        private FindService $findService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();
        $data = $this->findService->findByApiKey($params);

        $response
            ->getBody()
            ->write(
                json_encode($data)
            );

        return $response;
    }
}
