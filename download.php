<?php

declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';

use BohanCo\BingHomepageImage\Downloader;
use BohanCo\BingHomepageImage\LeanCloudRepository;
use BohanCo\BingHomepageImage\LeanCloudSDK\ArrayStorage;
use LeanCloud\Client;
use LeanCloud\User;

Client::initialize(
    getenv('LEANCLOUD_APP_ID'),
    getenv('LEANCLOUD_APP_KEY'),
    ''
);

Client::useMasterKey(false);

Client::setStorage(new ArrayStorage());

$sessionToken = getenv('LEANCLOUD_SESSION_TOKEN');

if ($sessionToken !== false) {
    Client::getStorage()->set('LC_SessionToken', $sessionToken);
    User::become($sessionToken);
}

$repository = new LeanCloudRepository();

$images = $repository->unreadyImages();

if (!empty($images)) {
    $downloader = new Downloader(getenv('DEST_DIR'));
    $downloader->download($images, [
        getenv('AWS_ACCESS_KEY_ID'),
        getenv('AWS_SECRET_ACCESS_KEY'),
        getenv('S3_ENDPOINT'),
        getenv('S3_BUCKET'),
        getenv('S3_FOLDER'),
        getenv('AWS_DEFAULT_REGION'),
    ]);
    $repository->setImagesReady(array_keys($images));
}
