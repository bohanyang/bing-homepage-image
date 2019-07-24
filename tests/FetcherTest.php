<?php

declare(strict_types=1);

namespace BohanCo\BingHomepageImage\Tests;

use BohanCo\BingHomepageImage\Fetcher;
use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class FetcherTest extends TestCase
{
    public function testDaysAgo() : void
    {
        foreach ([
            [
                '2019-05-31 23:59:59 Asia/Shanghai',
                '2019-07-23 15:04:05 Asia/Shanghai',
                53,
            ],
            [
                '2019-07-08 23:59:59 Asia/Shanghai',
                '2019-07-23 00:00:00 Asia/Tokyo',
                14,
            ],
            [
                '2019-10-27 00:00:00 Europe/London',
                '2019-10-26 23:59:59 UTC',
                0,
            ],
            [
                '2019-04-04 00:00:00 Europe/London',
                '2019-04-01 23:59:59 UTC',
                -2,
            ],
        ] as [$date, $today, $expected]) {
            $date  = DateTime::createFromFormat('Y-m-d H:i:s e', $date);
            $today = DateTime::createFromFormat('Y-m-d H:i:s e', $today);

            $this->assertSame($expected, Fetcher::daysAgo($date, $today));
        }
    }

    public function testDateBefore() : void
    {
        foreach ([
            [
                53,
                new DateTimeZone('Asia/Shanghai'),
                '2019-07-23 15:04:05 Asia/Shanghai',
                '2019-05-31 00:00:00',
            ],
            [
                14,
                new DateTimeZone('Asia/Shanghai'),
                '2019-07-23 00:00:00 Asia/Tokyo',
                '2019-07-08 00:00:00',
            ],
            [
                0,
                new DateTimeZone('Europe/London'),
                '2019-10-26 23:59:59 UTC',
                '2019-10-27 00:00:00',
            ],
            [
                -2,
                new DateTimeZone('Europe/London'),
                '2019-04-01 23:59:59 UTC',
                '2019-04-04 00:00:00',
            ],
        ] as [$index, $tz, $today, $expected]) {
            $today = DateTime::createFromFormat('Y-m-d H:i:s e', $today);
            $actual = Fetcher::dateBefore($index, $tz, $today);
            $this->assertSame("${expected} {$tz->getName()}", $actual->format('Y-m-d H:i:s e'));
        }
    }

    public function testParseFullStartDate() : void
    {
        foreach ([
            '201905221600' => '2019-05-23 00:00:00 +08:00',
            '201905230700' => '2019-05-23 00:00:00 -07:00',
            '201905221830' => '2019-05-23 00:00:00 +05:30',
            '201905221400' => '2019-05-23 00:00:00 +10:00',
        ] as $fullStartDate => $expected) {
            $date = Fetcher::parseFullStartDate((string) $fullStartDate);
            $this->assertSame($expected, $date->format('Y-m-d H:i:s P'));
        }
    }

    public function testExtractKeyword() : void
    {
        foreach ([
            'javascript:void(0);' => null,
            'http://www.msxiaona.cn/' => null,
            'https://bingdict.chinacloudsites.cn/download?tag=BDPDV' => null,
            'http://www.bing.com/search?q=%E5%BC%80%E6%99%AE%E6%A2%85%E8%8E%BA' => '开普梅莺',
            'https://www.baidu.com/s?q=&wd=%E5%8D%8E%E4%B8%BA' => '华为',
        ] as $url => $expected) {
            $keyword = Fetcher::extractKeyword($url);
            $this->assertSame($expected, $keyword);
        }
    }

    public function testParseUrlBase() : void
    {
        foreach ([
            '/az/hprichbg/rb/PineBough_ROW6233127332' => [
                'PineBough_ROW6233127332',
                'PineBough',
            ],
            '/az/hprichbg/rb/FlowerFes__JA-JP2679822467' => [
                'FlowerFes__JA-JP2679822467',
                'FlowerFes_',
            ],
            '/th?id=OHR.PingxiSky_EN-GB0458915063' => [
                'PingxiSky_EN-GB0458915063',
                'PingxiSky',
            ],
        ] as $urlBase => $expected) {
            $actual = Fetcher::parseUrlBase($urlBase);
            $this->assertSame($expected, $actual);
        }
    }

    public function testParseCopyright() : void
    {
        foreach ([
            'Un ourson noir dans un pin, Parc national Jasper, Alberta' .
            ' (Ursus americanus) (© Donald M. Jones/Minden Pictures)' => [
                'Un ourson noir dans un pin, Parc national Jasper, Alberta (Ursus americanus)',
                'Donald M. Jones/Minden Pictures',
            ],
            '来自人工智能的画作《思念》（© 微软小冰）' => [
                '来自人工智能的画作《思念》',
                '微软小冰',
            ],
            '｢国立科学博物館｣東京, 台東区（©　WindAwake/Shutterstock）' => [
                '｢国立科学博物館｣東京, 台東区',
                'WindAwake/Shutterstock',
            ],
        ] as $copyright => $expected) {
            $actual = Fetcher::parseCopyright($copyright);
            $this->assertSame($expected, $actual);
        }
    }
}
