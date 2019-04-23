<?php

namespace Dotmailer\Adapter;

use Dotmailer\Config\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use http\Exception\BadUrlException;
use Psr\Http\Message\ResponseInterface;

class GuzzleAdapter implements Adapter
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $baseUri
     *
     * @return self
     */
    public static function fromCredentials(string $username, string $password, string $baseUri = null): self
    {
        if ($baseUri === null) {
            if (class_exists('Dotmailer\Config\Settings')) {
                $baseUri = Settings::DEFAULT_URI;
            } else {
                $msg = "A base URI must be supplied in the method call or a settings class with a DEFAULT_URI constant must be available at namespace: Dotmailer\Config\Settings";
                throw new \UnexpectedValueException($msg, 100);
            }

        }
        $client = new Client([
                'base_uri' => $baseUri,
                'auth' => [
                    $username,
                    $password,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

        return new self($client);
    }

    /**
     * @inheritdoc
     */
    public function get(string $url, array $params = []): ResponseInterface
    {
        return $this->client->request('GET', $url, ['query' => $params]);
    }

    /**
     * @inheritdoc
     */
    public function post(string $url, array $content = []): ResponseInterface
    {
        return $this->client->request('POST', $url, ['json' => $content]);
    }

    /**
     * @inheritdoc
     */
    public function put(string $url, array $content = []): ResponseInterface
    {
        return $this->client->request('PUT', $url, ['json' => $content]);
    }

    /**
     * @inheritdoc
     */
    public function delete(string $url): ResponseInterface
    {
        return $this->client->request('DELETE', $url);
    }
}
