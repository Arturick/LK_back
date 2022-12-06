<?php

namespace App\Actions\BuyOut;

use App\Exceptions\AppException;
use App\Service\BuyOutService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class BuyOutAction
{
    public function __construct(
        private BuyOutService $buyOutService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $group = @$args['group'];
        $update = @$args['update'];

        $params = $request->getParsedBody();

        $output = [];

        if ( $group ) {
            $output = $this->buyOutService->getByGroup($group, $params);
        } else {
            $output = $this->buyOutService->list($params);
        }


        $response
            ->getBody()
            ->write(
                json_encode($output)
            );

        return $response;
    }
}
