<?php

use App\Actions\Positions\GetPositionsListAction;
use App\Actions\Main\OptionsAction;
use App\Actions\Notification\NotificationAction;
use App\Actions\Main\FastInfoAction;
use App\Actions\Main\StaInfoAction;
use App\Actions\Main\SmsAction;
use App\Actions\Main\UpdateAction;
use App\Actions\Main\AuthAction;
use App\Actions\Main\DaDataAction;
use App\Actions\Report\ReportAction;
use App\Actions\Accounts\AccountAction;




use App\Actions\Find\ByArtAction;
use App\Actions\Find\CheckQueryAction;
use App\Actions\Find\SplitByDateAction;
use App\Actions\Find\FindWbAction;
use App\Actions\Find\BulkAction;

use App\Actions\Order\OrderSaveAction;

use App\Actions\BuyOut\BuyOutAction;

use App\Actions\Delivery\DeliveryAction;
use App\Actions\Reviews\ReviewsAction;


use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;

return function (App $app) {
    $app->group('/api', function ($group) {
        $group->group('/options', function ($group) {
            $group->get('/', OptionsAction::class);
        });
        $group->group('/report', function ($group) {
            $group->get('/', ReportAction::class);
        });

        $group->group('/update', function ($group) {
            $group->get('/', UpdateAction::class);
        });
        $group->group('/positions', function ($group) {
            $group->get('/', GetPositionsListAction::class);
        });
        $group->group('/get_notification', function ($group) {
            $group->get('/', NotificationAction::class);
        });
        $group->group('/get_account', function ($group) {
            $group->get('/', AccountAction::class);
        });
        $group->group('/fastinfo', function ($group) {
            $group->get('/', FastInfoAction::class);
        });
        $group->group('/statnfo', function ($group) {
            $group->post('/', StaInfoAction::class);
        });
        $group->group('/buyout', function ($group) {
            $group->post('/', BuyOutAction::class);
            $group->post('/update', BuyOutAction::class);
            $group->post('/{group}', BuyOutAction::class);
        });



        $group->group('/delivery', function ($group) {
            $group->post('/', DeliveryAction::class);
            $group->post('/{group}', DeliveryAction::class);
        });

        $group->group('/reviews', function ($group) {
            $group->post('/', ReviewsAction::class);
            $group->post('/{group}', ReviewsAction::class);
        });



        $group->group('/find', function ($group) {
            $group->post('/bulk/', BulkAction::class);
            $group->post('/byart/', ByArtAction::class);
            $group->post('/checkquery/{type}/', CheckQueryAction::class);
            $group->post('/splitbydate/', SplitByDateAction::class);
            $group->post('/wb/', FindWbAction::class);
        });

        $group->group('/order', function ($group) {
            $group->post('/save/', OrderSaveAction::class);
        });

        $group->group('/sms', function ($group) {
            $group->post('/send/', SmsAction::class);
            $group->post('/check/', SmsAction::class);
        });

        $group->group('/auth', function ($group) {
            $group->post('/parent/{ptype}/', AuthAction::class);
            $group->post('/user/', AuthAction::class);
            $group->post('/user/{type}/', AuthAction::class);
        });
        $group->group('/dadata', function ($group) {
            $group->post('/search/', DaDataAction::class);
        });

    });

    //CORS
    $app->options('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });

    $app->add(function ($request, $handler) {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Token')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    });

    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
        throw new HttpNotFoundException($request);
    });
};
