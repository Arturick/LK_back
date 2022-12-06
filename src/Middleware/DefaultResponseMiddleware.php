<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DefaultResponseMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (count($response->getHeader('Content-Type')) === 0) {
            $response = $response->withHeader('Content-Type', 'application/json');
        }

        if ($response->getBody()->getSize() == 0) {
            $response->getBody()->write(
                json_encode([
                    'success' => true
                ])
            );
        }

        return $response;
    }
}
