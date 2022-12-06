<?php

namespace App\Actions\Find;

use App\Exceptions\AppException;
use App\Service\FindService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class ByArtAction
{
    public function __construct(
        private FindService $findService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();

        $response
            ->getBody()
            ->write(
                json_encode($this->findService->findByArt($params))
            );

        return $response;
    }
}
