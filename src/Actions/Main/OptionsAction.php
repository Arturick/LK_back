<?php

namespace App\Actions\Main;

use App\Exceptions\AppException;
use App\Service\OptionsService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class OptionsAction
{
    public function __construct(
        private OptionsService $optionsService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();

        $response
            ->getBody()
            ->write(
                json_encode($this->optionsService->list($params))
            );

        return $response;
    }
}
