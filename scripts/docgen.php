#!/usr/bin/env php
<?php

$shortoptions = '';
$longoptions = array('plugin=');


$helptext = <<<ENDOFHELP
Build HTML documentation from doc comments in source.

Usage: docgen.php [options] output-directory
Options:

  --plugin=...     build docs for given plugin instead of core


ENDOFHELP;

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
require_once INSTALLDIR.'/scripts/commandline.inc';

$pattern = "*.php *.inc";
$exclude = 'config.php */extlib/* */local/* */plugins/* */scripts/*';

if (isset($args[0])) {
    $outdir = $args[0];
    if (!is_dir($outdir)) {
        echo "Output directory $outdir is not a directory.\n";
        exit(1);
    }
} else {
    print $helptext;
    exit(1);
}

if (have_option('p', 'plugin')) {
    $plugin = get_option_value('plugin');
    $exclude = "*/extlib/*";
    $indir = INSTALLDIR . "/plugins/" . $plugin;
    if (!is_dir($indir)) {
        $indir = INSTALLDIR . "/plugins";
        $filename = "{$plugin}Plugin.php";
        if (!file_exists("$indir/$filename")) {
            echo "Can't find plugin $plugin.\n";
            exit(1);
        } else {
            $pattern = $filename;
        }
    }
} else {
    $indir = INSTALLDIR;
}

$replacements = array(
    '%%version%%' => STATUSNET_VERSION,
    '%%indir%%' => $indir,
    '%%pattern%%' => $pattern,
    '%%outdir%%' => $outdir,
    '%%htmlout%%' => $outdir,
    '%%exclude%%' => $exclude,
);

var_dump($replacements);

$template = file_get_contents(dirname(__FILE__) . '/doxygen.tmpl');
$template = strtr($template, $replacements);

$templateFile = tempnam(sys_get_temp_dir(), 'statusnet-doxygen');
file_put_contents($templateFile, $template);

$cmd = "doxygen " . escapeshellarg($templateFile);

$retval = 0;
passthru($cmd, $retval);

if ($retval == 0) {
    echo "Done!\n";
    unlink($templateFile);
    exit(0);
} else {
    echo "Failed! Doxygen config left in $templateFile\n";
    exit($retval);
}

