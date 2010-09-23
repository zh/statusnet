<?php

if (php_sapi_name() != 'cli') {
    die('no');
}

define('INSTALLDIR', dirname(dirname(dirname(dirname(__FILE__)))));

require INSTALLDIR . "/scripts/commandline.inc";

$runner = YammerRunner::init();

switch ($runner->state())
{
    case 'init':
        $url = $runner->requestAuth();
        echo "Log in to Yammer at the following URL and confirm permissions:\n";
        echo "\n";
        echo "    $url\n";
        echo "\n";
        echo "Pass the resulting code back by running:\n"
        echo "\n"
        echo "    php yammer-import.php --auth=####\n";
        echo "\n";
        break;

    case 'requesting-auth':
        if (empty($options['auth'])) {
            echo "Please finish authenticating!\n";
            break;
        }
        $runner->saveAuthToken($options['auth']);
        // Fall through...

    default:
        while (true) {
            echo "... {$runner->state->state}\n";
            if (!$runner->iterate()) {
                echo "... done.\n";
                break;
            }
        }
        break;
}