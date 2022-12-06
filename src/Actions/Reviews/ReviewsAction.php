<?php

namespace App\Actions\Reviews;

use App\Exceptions\AppException;
use App\Service\ReviewsService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class ReviewsAction
{
    public function __construct(
        private ReviewsService $reviewsService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {

        $group = @$args['group'];

        $params = $request->getParsedBody();

        $output = [];
        if ( @$group && preg_match('#^\d+$#', $group) ) {
            $output = $this->reviewsService->getByGroup($group, $params);
        } elseif ( $group == 'save' ) {
            $output = $this->reviewsService->save($params);
        } else {
            $output = $this->reviewsService->list($params);
        }
        

        $response
            ->getBody()
            ->write(
                json_encode($output)
            );

        return $response;
    }
}
