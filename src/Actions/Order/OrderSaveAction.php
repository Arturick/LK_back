<?php

namespace App\Actions\Order;

use App\Exceptions\AppException;
use App\Service\OrderService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class OrderSaveAction
{
    public function __construct(
        private OrderService $orderService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $params = $request->getParsedBody();

        $response
            ->getBody()
            ->write(
                json_encode($this->orderService->save($params))
            );

        return $response;
    }
}
