<?php

namespace App\Actions\Main;

use App\Exceptions\AppException;
use App\Service\AuthService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class AuthAction
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $output = [];
        $params = $request->getParsedBody();


        if ( @$args['type'] ) {
            if ( @$args['type'] == 'save' ) {
                $output = $this->authService->save($params);
            }
        } else if ( @$args['ptype'] ) {
            if ( @$args['ptype'] == 'get' ) {
                $output = $this->authService->getParentUsers($params);        
            }
            if ( @$args['ptype'] == 'save' ) {
                $output = $this->authService->saveParentUsers($params);        
            }
            if ( @$args['ptype'] == 'del' ) {
                $output = $this->authService->delParentUsers($params);        
            }
        } else {
            $output = $this->authService->getUserByJwt($params);
        }
        
        $response
            ->getBody()
            ->write(
                json_encode($output)
            );

        return $response;
    }
}
