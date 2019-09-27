<?php

declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';

use BohanCo\BingHomepageImage\Downloader;
use BohanCo\BingHomepageImage\LeanCloudRepository;
use LeanCloud\Client;

Client::initialize(
    getenv('LEANCLOUD_APP_ID'),
    getenv('LEANCLOUD_APP_KEY'),
    getenv('LEANCLOUD_APP_MASTER_KEY')
);

Client::useMasterKey(true);

$repository = new LeanCloudRepository();

$images = $repository->unreadyImages();

if (!empty($images)) {
    $downloader = new Downloader(getenv('DESTINATION_DIR'));
    $downloader->download($images);
    $repository->setImagesReady(array_keys($images));
}
