<?php

namespace App\Service;

class EmailService
{
    public function __construct(
        private HttpService $http
    ) {
    }

    public function send(
        array $to,
        string $subject,
        string $message,
        array $cc = [],
        array $bcc = [],
        ?string $replyTo = null,
        array $attachment = []
    ): bool {
        $response = $this->http->post(
            'https://initpro.ru/sendmailWithCustomHeaders.php',
            // 'https://dev.initpro.ru/sendmailWithCustomHeadersAndFile.php',
            [
                'to' => $to,
                'subject' => $subject,
                'message' => $message,
                'cc' => $cc,
                'bcc' => $bcc,
                'replyTo' => $replyTo,
                'attachment' => $attachment
            ]
        );
        return $response['success'];
    }
}
