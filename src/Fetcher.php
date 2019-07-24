<?php

declare(strict_types=1);

namespace BohanCo\BingHomepageImage;

use DateInterval;
use DateTime;
use DateTimeZone;

use function abs;
use function array_shift;
use function parse_str;
use function parse_url;
use function preg_match;
use function urldecode;

use const PHP_URL_QUERY;

class Fetcher
{
    public const TIMEZONES = [
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
        'en-AU' => 'America/Los_Angeles',
    ];

    protected static function getToday(?DateTimeZone $tz = null, ?DateTime $today = null) : DateTime
    {
        if (isset($today)) {
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
        $today = static::getToday($date->getTimezone(), $today);
        $diff = $date->setTime(0, 0, 0)->diff($today, false);

        return (int) $diff->format('%r%a');
    }

    /** Get the date "$index" days before today in "$tz". */
    public static function dateBefore(int $index, ?DateTimeZone $tz = null, ?DateTime $today = null) : DateTime
    {
        $today = static::getToday($tz, $today);
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
        if (preg_match($regex, $urlBase, $matches) === 1) {
            return $matches;
        }
    }

    /**
     * Extract image description as well as the author and/or
     * the stock photo agency from "copyright" string.
     *
     * @return string[]
     */
    public static function parseCopyright(string $copyright) : array
    {
        $regex = '/(.+?)(?: |\x{3000})?(?:\(|\x{FF08})\x{00A9}(?: |\x{3000})?(.+)(?:\)|\x{FF09})/u';

        $matches = [];
        if (preg_match($regex, $copyright, $matches) === 1) {
            array_shift($matches);

            return $matches;
        }
    }
}
