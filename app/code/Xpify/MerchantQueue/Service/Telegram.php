<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Service;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientExceptionInterface;
use Shopify\Clients\HttpHeaders;
use Shopify\Clients\HttpResponse;
use Psr\Http\Message\RequestInterface as IRequest;

class Telegram
{
    /**
     * The maximum number of characters allowed in a message according to the Telegram api documentation
     */
    private const MAX_MESSAGE_LENGTH = 4096;

    private Client $client;
    private const HEADERS = [
        HttpHeaders::CONTENT_TYPE => 'application/json',
        HttpHeaders::USER_AGENT => 'Omni Themes - System/1.0 (Xpify Merchant Queue Notifier)',
    ];
    private ?IRequest $request = null;
    private int $maxTries;

    public function __construct(string $botToken, string $chatId, int $tries = 3) {
        $this->client = new Client();
        $query = preg_replace("/%5B[0-9]+%5D/", "%5B%5D", http_build_query(['chat_id' => $chatId]));
        $url = (new Uri())
            ->withScheme('https')
            ->withHost("api.telegram.org")
            ->withPath("/bot{$botToken}/sendMessage")
            ->withQuery($query);
        $this->request = new Request('POST', $url, self::HEADERS);
        $this->maxTries = $tries;
    }

    /**
     * Send a message to the telegram chat group
     *
     * @param string $message
     * @return void
     * @throws ClientExceptionInterface
     */
    public function sendMessage(string $message): void
    {
        if (empty($message)) {
            return;
        }
        $messages = $this->handleMessageLength($message);
        foreach ($messages as $key => $msg) {
            if (empty($msg)) {
                continue;
            }
            if ($key > 0) {
                usleep(500000);
            }
            $this->send($msg);
        }
    }

    /**
     * @param string $message
     * @return HttpResponse|null
     * @throws ClientExceptionInterface
     */
    public function send(string $message): ?HttpResponse
    {
        $payload = [
            'text' => $message,
            'parse_mode' => 'HTML',
        ];
        $payloadString = json_encode($payload);
        $stream = Utils::streamFor(json_encode($payload));
        $request = $this->request
            ->withBody($stream)
            ->withHeader(HttpHeaders::CONTENT_LENGTH, mb_strlen($payloadString));

        $currentTries = 0;

        do {
            $currentTries++;

            $response = HttpResponse::fromResponse($this->client->sendRequest($request));

            if ($response->getStatusCode() === 429) {
                $retryAfter = $response->hasHeader(HttpHeaders::RETRY_AFTER)
                    ? $response->getHeaderLine(HttpHeaders::RETRY_AFTER)
                    : 1;

                usleep((int)($retryAfter * 1000000));
            } else {
                break;
            }
        } while ($currentTries < $this->maxTries);
        return $response;
    }

    /**
     * Handle a message that is too long: truncates or splits into several
     * @param string $message
     * @return string[]
     */
    private function handleMessageLength(string $message): array
    {
//        $truncatedMarker = ' (...truncated)';
//        if (!true && strlen($message) > self::MAX_MESSAGE_LENGTH) {
//            return [self::substr($message, 0, self::MAX_MESSAGE_LENGTH - strlen($truncatedMarker)) . $truncatedMarker];
//        }

        return str_split($message, self::MAX_MESSAGE_LENGTH);
    }

    private function substr(string $string, int $start, ?int $length = null): string
    {
        if (extension_loaded('mbstring')) {
            return mb_strcut($string, $start, $length);
        }

        return substr($string, $start, (null === $length) ? strlen($string) : $length);
    }
}
