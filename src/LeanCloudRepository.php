<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper;

use LeanCloud\ACL;
use LeanCloud\LeanObject;
use LeanCloud\Query;

class LeanCloudRepository
{
    /** @var ACL $acl */
    private $acl;

    public function __construct()
    {
        $acl = new ACL();
        $acl->setPublicReadAccess(true);
        $acl->setPublicWriteAccess(true);
        $this->acl = $acl;
    }

    public function insert(array $archives) : void
    {
        $images = [];
        $map = [];
        $imageNames = [];

        foreach ($archives as $i => $result) {
            $archive = new LeanObject('Archive');
            $archive->setACL($this->acl);
            $archive->set('market', $result['market']);
            $archive->set('date', $result['date']->format('Ymd'));
            $archive->set('info', $result['description']);
            if (isset($result['link'])) {
                $archive->set('link', $result['link']);
            }
            if (isset($result['hotspots'])) {
                $archive->set('hs', $result['hotspots']);
            }
            if (isset($result['messages'])) {
                $archive->set('msg', $result['messages']);
            }
            if (!isset($images[$result['image']['name']])) {
                $image = new LeanObject('Image');
                $image->setACL($this->acl);
                $image->set('name', $result['image']['name']);
                $image->set('urlbase', $result['image']['urlbase']);
                $image->set('copyright', $result['image']['copyright']);
                $image->set('wp', $result['image']['wp']);
                $image->set('available', false);
                if (isset($result['image']['vid'])) {
                    $image->set('vid', $result['image']['vid']);
                }
                $images[$result['image']['name']] = $image;
                $map[$result['image']['name']] = [];
                $imageNames[] = $result['image']['name'];
            }
            $archives[$i] = $archive;
            $map[$result['image']['name']][] = $i;
        }

        $query = (new Query('Image'))->containedIn('name', $imageNames);

        foreach ($query->find() as $image) {
            $imageName = $image->get('name');
            $images[$imageName] = $image;
        }

        foreach ($map as $imageName => $list) {
            foreach ($list as $i) {
                $archives[$i]->set('image', $images[$imageName]);
            }
        }

        LeanObject::saveAll($archives);
    }

    public function unreadyImages() : array
    {
        $query = new Query('Image');
        $query->equalTo('available', false);
        $images = [];
        foreach ($query->find() as $image) {
            $images[$image->get('urlbase')] = $image->get('wp');
        }

        return $images;
    }

    public function setImagesReady(array $images) : void
    {
        $results = (new Query('Image'))->containedIn('urlbase', $images)->find();
        foreach ($results as $object) {
            $object->set('available', true);
        }
        LeanObject::saveAll($results);
    }
}
