<?php

namespace App\Middleware;

use Slim\Exception\HttpUnauthorizedException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Service\AuthService;

use App\Support\Auth;

class AuthMiddleware implements Middleware
{

    public function __construct(
        private AuthService $authService
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() !== 'OPTIONS') {
            $secret = config('app.secret');
            $token = $request->getHeader('Token');

            if (array_shift($token) !== $secret) {
                throw new HttpUnauthorizedException($request);
            }

            $authorization = $request->getHeaderLine('Authorization');
            if ( @$authorization ) {
                $jwt = str_replace('Bearer ', '', $authorization);
                $jwt = str_replace('Bearer', '', $jwt);

                $salt = config('app.jwt_salt');
                $_user = [];
                if ( $jwt ) {
                    $decoded = JWT::decode($jwt, new Key($salt, 'HS256'));
                    $_user = (array) $decoded;
                    if ( $_user['id'] ) {
                        Auth::init($_user['id']);
                    }
                }


            }
            
        }

        return $handler->handle($request);
    }
}
