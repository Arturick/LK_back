<?php

namespace App\Actions\Positions;

use App\Exceptions\AppException;
use App\Service\PositionsService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class GetPositionsListAction
{
    public function __construct(
        private PositionsService $positionsService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody();
        $response
            ->getBody()
            ->write(
                json_encode($this->positionsService->get())
            );

        return $response;
    }
}
