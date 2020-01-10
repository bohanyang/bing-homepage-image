<?php

declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use BohanCo\BingHomepageImage\Downloader;
use BohanCo\BingHomepageImage\LeanCloudRepository;
use BohanCo\BingHomepageImage\LeanCloudSDK\ArrayStorage;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Replicate\ReplicateAdapter;
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
    $client = new S3Client([
        'credentials' => [
            'key'    => getenv('AWS_ACCESS_KEY_ID'),
            'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
        ],
        'endpoint' => getenv('S3_ENDPOINT'),
        'region' => getenv('AWS_DEFAULT_REGION'),
        'version' => 'latest',
    ]);
    $localAdapter = new Local(getenv('DEST_DIR'));
    $s3Adapter = new AwsS3Adapter($client, getenv('S3_BUCKET'), getenv('S3_FOLDER'), [
        'CacheControl' => 'max-age=31536000',
        'ACL' => ''
    ]);
    $adapter = new ReplicateAdapter($localAdapter, $s3Adapter);
    $downloader = new Downloader(new Filesystem($adapter));
    $downloader->download($images);
    $repository->setImagesReady(array_keys($images));
}
