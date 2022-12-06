<?php

namespace App\Actions\Find;

use App\Exceptions\AppException;
use App\Service\FindService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class BulkAction
{
    public function __construct(
        private FindService $findService
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {


        $tmp = '/tmp';

        $params = $request->getParsedBody();

        $uploadedFiles = $request->getUploadedFiles();

        if ( count($uploadedFiles) > 0 ) {
            $params['files'] = [];
            foreach ($uploadedFiles as $file) {
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $filename = $this->findService->moveUploadedFile($tmp, $file);
                    $params['files'][] = $tmp.'/'.$filename;
                }
            }
        }


        $response
            ->getBody()
            ->write(
                json_encode($this->findService->bulk($params))
            );

        return $response;
    }
}
