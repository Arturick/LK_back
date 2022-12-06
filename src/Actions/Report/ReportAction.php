<?php

namespace App\Actions\Report;

use App\Exceptions\AppException;
use App\Service\ReportService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class ReportAction
{
    public function __construct(
        private ReportService $reportService
    ) {
    }

public function __invoke(Request $request, Response $response, array $args): Response
{

    $params = $request->getParsedBody();

    $response
        ->getBody()
        ->write(
            json_encode($this->reportService->save($params))
        );

    return $response;
}
}
