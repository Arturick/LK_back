<?php

namespace App\Actions\Delivery;

use App\Exceptions\AppException;
use App\Service\DeliveryService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class DeliveryAction
{
    public function __construct(
        private DeliveryService $deliveryService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $group = @$args['group'];

        $params = $request->getParsedBody();

        $output = [];
        if ( $group ) {
            $output = $this->deliveryService->getByGroup($group, $params);
        } else {
            $output = $this->deliveryService->list($params);
        }
        

        $response
            ->getBody()
            ->write(
                json_encode($output)
            );

        return $response;
    }
}
