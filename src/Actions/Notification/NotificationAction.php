<?php

namespace App\Actions\Notification;

use App\Exceptions\AppException;
use App\Service\NotificationService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class NotificationAction
{
    public function __construct(
        private NotificationService $notfService
    ) {
    }

public function __invoke(Request $request, Response $response, array $args): Response
{
    $params = $request->getParsedBody();
    $response
        ->getBody()
        ->write(
            json_encode($this->notfService->get())
        );

    return $response;
}
}
