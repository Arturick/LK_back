<?php

namespace App\Actions\Account;

use App\Exceptions\AppException;
use App\Service\AccountService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class AccountAction
{
    public function __construct(
        private AccountService $AccountOutService
    ) {
    }

public function __invoke(Request $request, Response $response, array $args): Response
{

    $group = @$args['group'];

    $params = $request->getParsedBody();

    $output = [];

    $output = $this->AccountOutService->list($params);



    $response
        ->getBody()
        ->write(
            json_encode($output)
        );

    return $response;
}
}
