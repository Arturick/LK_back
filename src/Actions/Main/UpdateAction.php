<?php
namespace App\Actions\Main;
use App\Service\UpdateService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class UpdateAction
{
public function __construct(
private UpdateService $updateService
) {
}

public function __invoke(Request $request, Response $response, array $args): Response
{



    $output = $this->updateService->dbUpdate();
    $response
        ->getBody()
        ->write(
            json_encode($output)
        );

    return $response;
}
}
