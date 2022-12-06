<?php

namespace App\Actions\Main;

use App\Exceptions\AppException;
use App\Service\SmsService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class SmsAction
{
    public function __construct(
        private SmsService $smsService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();

        $response
            ->getBody()
            ->write(
                json_encode($this->smsService->action($params))
            );

        return $response;
    }
}
