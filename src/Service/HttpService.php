<?php

namespace App\Service;

use App\Exceptions\AppException;
use GuzzleHttp\Client;

class HttpService
{
    private array $options = [];

    public function __construct(
        private Client $client
    ) {
    }

    /**
     * Отправить GET запрос
     * 
     * @param string $url адрес
     * @param array $query массив ключ-значение GET-параетров запроса
     * 
     * @return array ответ в виде ассоциативного массива
     * 
     * @throws AppException
     */
    public function get(string $url, array $query = []): array
    {
        $this->setRequestOption('query', $query);

        return $this->send('GET', $url);
    }

    /**
     * Отправить POST запрос
     * 
     * @param string $url адрес
     * @param array $json массив передаваемый в теле запроса
     * @param array $query массив ключ-значение GET-параетров запроса
     * 
     * @return array ответ в виде ассоциативного массива
     * 
     * @throws AppException
     */
    public function post(string $url, array $json, array $query = []): array
    {
        $this->setRequestOption('json', $json);
        $this->setRequestOption('query', $query);

        return $this->send('POST', $url);
    }

    /**
     * Установить дополнительные опции запроса
     */
    private function setRequestOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * Отправить запрос и разобрать ответ
     */
    private function send(string $method, string $url): array
    {
        $response = $this->client->request($method, $url, $this->options);

        $this->options = [];

        $code = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($code >= 300) {
            throw new AppException("response from $url has response code $code with body: $body");
        }

        $parsedBody = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AppException("cant parse json from $url with body: $body");
        }

        return $parsedBody;
    }
}
