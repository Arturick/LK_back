<?php

use Slim\App;
use App\Middleware\AuthMiddleware;
use App\Middleware\DefaultResponseMiddleware;
use App\Handlers\ErrorHandler;

return function (App $app) {
    $app->addBodyParsingMiddleware();

    $app->addRoutingMiddleware();

    $app->add(AuthMiddleware::class);
    $app->add(DefaultResponseMiddleware::class);

    $displayErrorDetails = config('app.displayErrorDetails');
    $errorHandler = new ErrorHandler($app);

    $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, false, false);
    $errorMiddleware->setDefaultErrorHandler($errorHandler);
};
