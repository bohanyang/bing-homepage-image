<?php

declare(strict_types=1);

namespace BohanCo\BingHomepageImage;

use DateInterval;
use DateTime;
use DateTimeZone;
use GuzzleHttp;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OutOfRangeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use Throwable;
use UnexpectedValueException;

use function abs;
use function array_shift;
use function json_decode;
use function parse_str;
use function parse_url;
use function preg_match;
use function urldecode;

use const PHP_URL_QUERY;

final class BingClient
{
    public const TIMEZONES = [
        'ROW' => 'America/Los_Angeles',
        'en-US' => 'America/Los_Angeles',
        'pt-BR' => 'America/Los_Angeles',
        'en-CA' => 'America/Toronto',
        'fr-CA' => 'America/Toronto',
        'en-GB' => 'Europe/London',
        'fr-FR' => 'Europe/Paris',
        'de-DE' => 'Europe/Berlin',
        'en-IN' => 'Asia/Kolkata',
        'zh-CN' => 'Asia/Shanghai',
        'ja-JP' => 'Asia/Tokyo',
        'en-AU' => 'Australia/Sydney',
    ];

    public static function getToday(?DateTimeZone $tz = null, ?DateTime $today = null) : DateTime
    {
        if ($today !== null) {
            $tz = $tz ?? $today->getTimezone();
            $today->setTimezone($tz)->setTime(0, 0, 0);
        } else {
            $today = new DateTime('today', $tz);
        }

        return $today;
    }

    /** Get how many days ago was "$date". */
    public static function daysAgo(DateTime $date, ?DateTime $today = null) : int
    {
        $today = self::getToday($date->getTimezone(), $today);
        $diff = $date->setTime(0, 0, 0)->diff($today, false);

        return (int) $diff->format('%r%a');
    }

    /** Get the date "$index" days before today in "$tz". */
    public static function dateBefore(int $index, ?DateTimeZone $tz = null, ?DateTime $today = null) : DateTime
    {
        $today = self::getToday($tz, $today);
        $invert = $index < 0 ? 1 : 0;
        $index = (string) abs($index);
        $interval = new DateInterval("P${index}D");
        $interval->invert = $invert;

        return $today->sub($interval);
    }

    /**
     * Parse "fullstartdate" string into DateTime
     * with correct time zone of UTC offset type.
     */
    public static function parseFullStartDate(string $fullStartDate) : DateTime
    {
        $d = DateTime::createFromFormat('YmdHi', $fullStartDate, new DateTimeZone('UTC'));

        if ($d === false) {
            throw new InvalidArgumentException("Failed to parse full start date ${fullStartDate}.");
        }

        if ((int) $d->format('G') < 12) {
            $tz = '-' . $d->format('H:i');
        } else {
            $d24 = (clone $d)->modify('+1 day midnight');
            $tz = $d->diff($d24, true)->format('%R%H:%I');
            $d = $d24;
        }

        return new DateTime($d->format('Y-m-d'), new DateTimeZone($tz));
    }

    /**
     * Parse an URL of web search engine and
     * extract keyword from its query string.
     */
    public static function extractKeyword(string $url) : ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (!$query) {
            return null;
        }

        parse_str($query, $query);

        $fields = ['q', 'wd'];

        foreach ($fields as $field) {
            if (isset($query[$field]) && $query[$field] !== '') {
                return urldecode($query[$field]);
            }
        }

        return null;
    }

    /**
     * Normalize "urlbase" and extract image name from it.
     * Returns "[$normalizedUrlBase, $imageName]".
     *
     * @return string[]
     */
    public static function parseUrlBase(string $urlBase) : array
    {
        $regex = '/(\w+)_(?:ROW|[A-Z]{2}-[A-Z]{2})\d+/';
        $matches = [];

        if (preg_match($regex, $urlBase, $matches) !== 1) {
            throw new InvalidArgumentException("Failed to parse URL base ${urlBase}.");
        }

        return $matches;
    }

    /**
     * Extract image description as well as the author and/or
     * the stock photo agency from "copyright" string.
     *
     * @return string[]
     */
    public static function parseCopyright(string $copyright) : array
    {
        $regex = '/(.+?)(?: |\x{3000})?(?:\(|\x{FF08})?\x{00A9}(?: |\x{3000})?(.+?)(?:\)|\x{FF09})?$/u';
        $matches = [];

        if (preg_match($regex, $copyright, $matches) !== 1) {
            throw new InvalidArgumentException("Failed to parse copyright string ${copyright}.");
        }

        array_shift($matches);

        return $matches;
    }

    /** @var string */
    private $endpoint;

    /** @var Client */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        string $endpoint = 'https://global.bing.com/HPImageArchive.aspx',
        ?LoggerInterface $logger = null,
        ?MessageFormatter $formatter = null
    ) {
        $this->endpoint = $endpoint;
        $this->logger = $logger ?? new Logger(self::class, [new StreamHandler('php://stderr')]);

        $formatter = $formatter ?? new MessageFormatter();

        $handler = new HandlerStack(GuzzleHttp\choose_handler());
        $handler->push(Middleware::httpErrors(), 'http_errors');
        $handler->push(Middleware::redirect(), 'allow_redirects');
        $handler->push(GuzzleMiddleware::retry(), 'retry');
        $handler->push(Middleware::log($this->logger, $formatter, LogLevel::DEBUG));

        $this->client = new Client(['handler' => $handler]);
    }

    private function request(string $market, int $index = 0, int $n = 1) : PromiseInterface
    {
        return $this->client->getAsync($this->endpoint, [
            'query' => [
                'format' => 'js',
                'idx' => (string) $index,
                'n' => (string) $n,
                'video' => '1',
                'mkt' => $market,
            ],
        ]);
    }

    private function get(string $market, ?DateTime $date = null, ?DateTimeZone $tz = null) : PromiseInterface
    {
        if ($tz === null) {
            if (!isset(self::TIMEZONES[$market])) {
                return new RejectedPromise(new InvalidArgumentException(
                    'Unknown market with no timezone provided.'
                ));
            }

            $tz = new DateTimeZone(self::TIMEZONES[$market]);
        }

        $date = $date ? new DateTime($date->format('Y-m-d'), $tz) : self::getToday($tz);

        $dateString = $date->format('Y-m-d');

        $offset = self::daysAgo($date);

        if ($offset < 0 || $offset > 7) {
            return new RejectedPromise(new OutOfRangeException(
                "The date ${dateString} in timezone {$tz->getName()} (UTC" .
                timezone_offset_name_get($tz) .
                ") has offset ${offset} which is out of the available range (0 to 7)."
            ));
        }

        return $this->request($market, $offset)->then(
            function (ResponseInterface $response) use ($market, $dateString, $offset) {
                $response = json_decode((string) $response->getBody());

                if (empty($response->images[0])) {
                    throw new UnexpectedValueException(
                        "Failed to parse response on date ${dateString} (offset ${offset})."
                    );
                }

                $response = $response->images[0];
                $response->market = $market;
                $response->fullstartdate = self::parseFullStartDate($response->fullstartdate);
                $response->date = $response->fullstartdate->format('Y-m-d');

                if ($response->date !== $dateString) {
                    throw new UnexpectedValueException(
                        "Got unexpected date $response->date (UTC" .
                        timezone_offset_name_get($response->fullstartdate->getTimezone()) .
                        ") instead of ${dateString} (offset ${offset})."
                    );
                }

                return $response;
            }
        );
    }

    public function fetch(string $market, ?DateTime $date = null, ?DateTimeZone $tz = null) : stdClass
    {
        try {
            $result = $this->get($market, $date, $tz)->wait();
        } catch (Throwable $e) {
            $this->logger->log(
                LogLevel::CRITICAL,
                "Error occurred while fetching for market ${market}: " . (string) $e
            );
            throw $e;
        }

        return $result;
    }

    /**
     * @param string[] $markets
     *
     * @return stdClass[]
     */
    public function batch(array $markets, ?DateTime $date = null) : array
    {
        $date = $date ?? self::getToday(new DateTimeZone('America/Los_Angeles'));

        /** @var PromiseInterface[] $promises */
        $promises = [];

        foreach ($markets as $market) {
            $promises[$market] = $this->get($market, $date);
        }

        $promises = Promise\settle($promises)->wait();
        $results = [];
        $failed = false;

        foreach ($promises as $market => $promise) {
            if ($promise['state'] === PromiseInterface::FULFILLED) {
                $results[$market] = $promise['value'];
            } else {
                $failed = true;
                $this->logger->log(
                    LogLevel::CRITICAL,
                    "Error occurred while fetching for market ${market}: " . (string) $promise['reason']
                );
            }
        }

        if ($failed) {
            throw new RuntimeException('Batch operation failed.');
        }

        return $results;
    }
}
