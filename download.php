<?php

declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use BohanYang\BingWallpaper\Downloader;
use BohanYang\BingWallpaper\LeanCloudRepository;
use BohanYang\BingWallpaper\LeanCloudSDK\ArrayStorage;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use LeanCloud\Client;
use LeanCloud\User;

$apiServer = getenv('LEANCLOUD_API_SERVER');

if ($apiServer !== false) {
    Client::setServerUrl($apiServer);
}

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
    $client2 = new S3Client([
        'credentials' => [
            'key'    => getenv('AWS_ACCESS_KEY_ID_2'),
            'secret' => getenv('AWS_SECRET_ACCESS_KEY_2'),
        ],
        'endpoint' => getenv('S3_ENDPOINT_2'),
        'region' => getenv('AWS_DEFAULT_REGION_2'),
        'version' => 'latest',
        'bucket_endpoint' => false,
        'use_path_style_endpoint' => true,
    ]);
    $adapter = new AwsS3Adapter($client2, getenv('S3_BUCKET_2'), getenv('S3_FOLDER_2'), [
        'CacheControl' => 'max-age=31536000',
        'ContentType' => 'image/jpeg',
        'ACL' => 'public-read'
    ]);
    $downloader = new Downloader(new Filesystem($adapter));
    $downloader->download($images);
    $repository->setImagesReady(array_keys($images));
}
