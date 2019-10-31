<?php

declare(strict_types=1);

namespace BohanCo\BingHomepageImage;

use LeanCloud\ACL;
use LeanCloud\LeanObject;
use LeanCloud\Query;
use stdClass;

class LeanCloudRepository
{
    /** @var ACL $acl */
    private $acl;

    public function __construct()
    {
        $acl = new ACL();
        $acl->setPublicReadAccess(true);
        $acl->setPublicWriteAccess(false);
        $this->acl = $acl;
    }

    /** @param stdClass[] $archives */
    public function insert(array $archives) : void
    {
        $images = [];
        $map = [];
        $imageNames = [];

        foreach ($archives as $i => $result) {
            $archive = new LeanObject('Archive');
            $archive->setACL($this->acl);
            $archive->set('market', $result->market);
            $archive->set('date', $result->fullstartdate->format('Ymd'));
            if (!empty($result->hs)) {
                $archive->set('hs', $result->hs);
            }
            if (!empty($result->msg)) {
                $archive->set('hs', $result->msg);
            }
            [$info, $copyright] = BingClient::parseCopyright($result->copyright);
            $archive->set('info', $info);
            $archive->set('link', $result->copyrightlink);
            [$urlBase, $imageName] = BingClient::parseUrlBase($result->urlbase);
            if (!isset($images[$imageName])) {
                $image = new LeanObject('Image');
                $image->setACL($this->acl);
                $image->set('name', $imageName);
                $image->set('urlbase', '/az/hprichbg/rb/' . $urlBase);
                $image->set('wp', $result->wp);
                $image->set('copyright', $copyright);
                $image->set('available', false);
                if (!empty($result->vid)) {
                    $image->set('vid', json_decode(json_encode($result->vid), true));
                }
                $images[$imageName] = $image;
                $map[$imageName] = [];
                $imageNames[] = $imageName;
            }
            $archives[$i] = $archive;
            $map[$imageName][] = $i;
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
