<?php

declare(strict_types=1);

namespace BohanCo\BingHomepageImage;

use Aws\S3\S3Client;
use GuzzleHttp;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;
use RuntimeException;

use function array_keys;
use function unlink;

use const CURLOPT_FOLLOWLOCATION;

final class Downloader
{
    public const DEFAULT_SIZES = [
        '1920x1080',
        '1080x1920',
        '1366x768',
        '768x1280',
        '800x480',
        '480x800',
    ];

    public const HIGH_RES = '1920x1200';

    /** @var string */
    private $saveDir;

    /** @var Client */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        string $saveDir,
        string $endpoint = 'https://www.bing.com/',
        ?LoggerInterface $logger = null,
        ?MessageFormatter $formatter = null
    ) {
        $this->saveDir = $saveDir;
        $this->logger = $logger ?? new Logger(self::class, [new StreamHandler('php://stderr')]);

        $formatter = $formatter ?? new MessageFormatter();

        $handler = new HandlerStack(GuzzleHttp\choose_handler());
        $handler->push(GuzzleMiddleware::downloader());
        $handler->push(Middleware::httpErrors(), 'http_errors');
        $handler->push(GuzzleMiddleware::retry(function (int $status) {
            return $status === 302 || $status >= 500 || $status === 408;
        }), 'retry');
        $handler->push(Middleware::log($this->logger, $formatter, LogLevel::DEBUG));

        $this->client = new Client([
            'handler' => $handler,
            'base_uri' => $endpoint,
            'curl' => [CURLOPT_FOLLOWLOCATION => false],
        ]);
    }

    public function download(array $images, array $s3 = []) : bool
    {
        $promises = [];
        foreach ($images as $urlBase => $wp) {
            $sizes = self::DEFAULT_SIZES;
            if ($wp) {
                $sizes[] = self::HIGH_RES;
            }
            foreach ($sizes as $size) {
                $filename = "${urlBase}_${size}.jpg";
                $promises[$filename] = $this->client->getAsync($filename, [
                    'sink' => $this->saveDir . $filename,
                ]);
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
            foreach (array_keys($promises) as $filename) {
                unlink($this->saveDir . $filename);
            }
            throw new RuntimeException('Download operation failed.');
        }

        if ($s3 !== []) {
            $s3client = new S3Client([
                'credentials' => [
                    'key'    => $s3[0],
                    'secret' => $s3[1],
                ],
                'endpoint' => $s3[2],
                'region' => $s3[5],
                'version' => '2006-03-01',
            ]);
            foreach (array_keys($promises) as $filename) {
                $s3client->putObject([
                    'Bucket' => $s3[3],
                    'Key' => "{$s3[4]}${filename}",
                    'SourceFile' => $this->saveDir . $filename,
                    'CacheControl' => 'max-age=31536000',
                ]);
            }
        }

        return true;
    }
}
