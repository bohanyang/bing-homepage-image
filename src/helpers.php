<?php

declare(strict_types=1);

namespace BohanCo\BingHomepageImage;

use DateTime;
use DateTimeInterface;
use DateTimeZone;

use function abs;
use function floor;
use function str_pad;

use const STR_PAD_LEFT;

/** Copied from CarbonTimeZone->toOffsetName() */
function timezone_offset_name_get(DateTimeZone $tz, ?DateTimeInterface $date = null) : string
{
    $minutes = floor($tz->getOffset($date ?: new DateTime('now', $tz)) / 60);

    $hours = floor($minutes / 60);

    $minutes = str_pad((string) (abs($minutes) % 60), 2, '0', STR_PAD_LEFT);

    return ($hours < 0 ? '-' : '+') . str_pad((string) abs($hours), 2, '0', STR_PAD_LEFT) . ":$minutes";
}
