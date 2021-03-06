<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper;

use GuzzleHttp;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use League\Flysystem\Filesystem;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use function Safe\fclose as fclose;
use function Safe\rewind as rewind;
use function Safe\tmpfile as tmpfile;

final class Downloader
{
    public const DEFAULT_SIZES = [
        '1920x1080',
        '1080x1920',
        '1366x768',
        '768x1280',
        '800x480',
        '480x800',
        'UHD'
    ];

    public const HIGH_RES = '1920x1200';

    /** @var Filesystem */
    private $fs;

    /** @var Client */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Filesystem $fs,
        string $endpoint = 'https://www.bing.com/th?id=OHR.',
        ?LoggerInterface $logger = null,
        ?MessageFormatter $formatter = null
    )
    {
        $this->fs = $fs;
        $this->logger = $logger ?? new Logger(self::class, [new StreamHandler('php://stderr')]);

        $formatter = $formatter ?? new MessageFormatter();

        $handler = new HandlerStack(GuzzleHttp\choose_handler());
        $handler->push(GuzzleMiddleware::ensure());
        $handler->push(Middleware::httpErrors(), 'http_errors');
        $handler->push(GuzzleMiddleware::retry(function (int $status) {
            return $status === 302 || $status >= 500 || $status === 408;
        }), 'retry');
        $handler->push(Middleware::log($this->logger, $formatter, LogLevel::DEBUG));

        $this->client = new Client([
            'handler' => $handler,
        ]);
    }

    public function download(array $images)
    {
        $promises = [];
        foreach ($images as $urlBase => $wp) {
            $urlBase = substr($urlBase, 16);
            $sizes = self::DEFAULT_SIZES;
            if ($wp) {
                $sizes[] = self::HIGH_RES;
            }
            foreach ($sizes as $size) {
                $filename = "${urlBase}_${size}.jpg";
                $stream = tmpfile();
                $promises[$filename] = $this->client->getAsync($endpoint . $filename, [
                    'sink' => $stream
                ])->then(function () use ($filename, $stream) {
                    rewind($stream);
                    $this->fs->putStream($filename, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                });
            }
        }
        $promises = Promise\settle($promises)->wait();
        $failed = false;
        foreach ($promises as $filename => $promise) {
            if ($promise['state'] === PromiseInterface::REJECTED) {
                $failed = true;
                $this->logger->log(
                    LogLevel::CRITICAL,
                    "Error occurred while downloading ${filename}: " . (string) $promise['reason']
                );
            }
        }

        if ($failed) {
            throw new RuntimeException('Download operation failed.');
        }
    }
}
