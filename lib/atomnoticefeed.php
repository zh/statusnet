<?php

class AtomNoticeFeed extends Atom10Feed
{
    function __construct($indent = true) {
        parent::__construct($indent);

        // Feeds containing notice info use these namespaces

        $this->addNamespace(
            'xmlns:thr',
            'http://purl.org/syndication/thread/1.0'
        );

        $this->addNamespace(
            'xmlns:georss',
            'http://www.georss.org/georss'
        );

        $this->addNamespace(
            'xmlns:activity',
            'http://activitystrea.ms/spec/1.0/'
        );

        // XXX: What should the uri be?
        $this->addNamespace(
            'xmlns:ostatus',
            'http://ostatus.org/schema/1.0'
        );
    }

    function addEntryFromNotices($notices)
    {
        if (is_array($notices)) {
            foreach ($notices as $notice) {
                $this->addEntryFromNotice($notice);
            }
        } else {
            while ($notices->fetch()) {
                $this->addEntryFromNotice($notice);
            }
        }
    }

    function addEntryFromNotice($notice)
    {
        $this->addEntryRaw($notice->asAtomEntry());
    }

}