<?php

namespace App\Actions\Find;

use App\Exceptions\AppException;
use App\Service\FindService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class CheckQueryAction
{
    public function __construct(
        private FindService $findService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();

        if ( @$args['type'] ) {
            if ( @$args['type'] == 'all' ) {
                $data = $this->findService->checkAllQuery($params);
            }
        } else {
            $data = $this->findService->checkQuery($params);
        }

        $response
            ->getBody()
            ->write(
                json_encode($data)
            );

        return $response;
    }
}
