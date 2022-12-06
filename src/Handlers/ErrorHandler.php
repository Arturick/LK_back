<?php

namespace App\Handlers;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Interfaces\ErrorHandlerInterface;

class ErrorHandler implements ErrorHandlerInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(App $app)
    {
        $this->responseFactory = $app->getResponseFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $code = $exception->getCode();

        if (!is_numeric($code) || $code == 0 || $code > 599) {
            $code = 500;
        }

        $response = $this->responseFactory->createResponse($code);
        $response->getBody()->write(
            json_encode(
                [
                    'success' => false,
                    'error' => $exception->getMessage(),
                    'tarce' => $exception->getTrace()
                ],
                JSON_UNESCAPED_UNICODE
            )
        );

        return $response->withHeader('Content-Type', 'application/json');
    }
}
