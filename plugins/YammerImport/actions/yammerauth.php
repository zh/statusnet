<?php


function showYammerAuth()
{
    $token = $yam->requestToken();
    $url = $yam->authorizeUrl($token);

    // We're going to try doing this in an iframe; if that's not happy
    // we can redirect but there doesn't seem to be a way to get Yammer's
    // oauth to call us back instead of the manual copy. :(

    //common_redirect($url, 303);
    $this->element('iframe', array('id' => 'yammer-oauth',
                                   'src' => $url));
}

