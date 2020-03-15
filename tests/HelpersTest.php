<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper\Tests;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

use function BohanYang\BingWallpaper\timezone_offset_name_get;

final class HelpersTest extends TestCase
{
    public function testTimezoneOffsetNameGet() : void
    {
        foreach ([
            [new DateTimeZone('Asia/Kolkata'), null, '+05:30'],
            [
                new DateTimeZone('Europe/London'),
                new DateTime('2019-03-31 18:00:00', new DateTimeZone('America/Los_Angeles')),
                '+01:00',
            ],
            [
                new DateTimeZone('America/Los_Angeles'),
                new DateTime('2019-11-03 17:00:00', new DateTimeZone('Asia/Shanghai')),
                '-08:00',
            ],
        ] as [$tz, $date, $expected]) {
            $this->assertSame($expected, timezone_offset_name_get($tz, $date));
        }
    }
}
