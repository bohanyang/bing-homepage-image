<?php

declare(strict_types=1);

namespace BohanCo\BingHomepageImage;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;

final class GuzzleMiddleware
{
    public static function retry(
        ?callable $statusDecider = null,
        int $maxRetries = 3,
        ?callable $delay = null
    ) : callable {
        if ($statusDecider === null) {
            $statusDecider = function (int $status) {
                return $status >= 500 || $status === 408;
            };
        }

        return Middleware::retry(
            function ($retries, $request, ?ResponseInterface $response, $reason) use ($maxRetries, $statusDecider) : bool {
                if ($retries >= $maxRetries) {
                    return false;
                }

                if ($reason instanceof ConnectException) {
                    return true;
                }

                if ($response !== null) {
                    if ($statusDecider($response->getStatusCode())) {
                        return true;
                    }
                }

                return false;
            },
            $delay
        );
    }
}
