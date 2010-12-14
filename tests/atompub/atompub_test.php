<?php

class AtomPubClient
{
    /**
     *
     * @param string $url collection feed URL
     * @param string $user auth username
     * @param string $pass auth password
     */
    function __construct($url, $user, $pass)
    {
        $this->url = $url;
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * @param string $baseUrl to attempt feed discovery from
     * @return AtomPubClient
     */
    static function discoverFromPage($baseUrl)
    {
        
    }

    function get()
    {
        
    }
    
    function post($stuff, $type='application/atom+xml;type=entry')
    {
        // post it up!
        // optional 'Slug' header too
        // .. receive ..
        if ($response->getStatus() == '201') {
            // yay
            // MUST have a "Location" header
            // if it has a Content-Location header, it MUST match Location
            //   and if it does, check the response body -- it should match what we posted, more or less.
        } else {
            throw new Exception("Expected HTTP 201 on POST, got " . $response->getStatus());
        }
    }

    function put($data, $type='application/atom+xml;type=entry')
    {
        // PUT it up!
        // must get a 200 back.
        // unlike post, we don't get the location too.
    }
}

// discover the feed...

// confirm the feed has edit links ..... ?

$pub = new AtomPubClient($url, $user, $pass);

// post!
$target = $pub->post($atom);

// make sure it's accessible
// fetch $target -- should give us the atom entry
//  edit URL should match

// refetch the feed; make sure the new entry is in there
//  edit URL should match

// try editing! it should fail.
try {
    $target2 = $pub->put($target, $atom2);
    // FAIL! this shouldn't work.
} catch (AtomPubPermissionDeniedException $e) {
    // yay
}

// try deleting!
$pub->delete();

// fetch $target -- it should be gone now

// fetch the feed again; the new entry should be gone again





// make subscriptions
// make some posts
// make sure the posts go through or not depending on the subs
// remove subscriptions
// test that they don't go through now

// group memberships too




// make sure we can't post to someone else's feed!
// make sure we can't delete someone else's messages
// make sure we can't create/delete someone else's subscriptions
// make sure we can't create/delete someone else's group memberships

