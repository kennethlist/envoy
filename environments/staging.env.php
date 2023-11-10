<?php
// setup variables per environment
// inherits from config.php

# Website urls
$website_url = 'https://test.example.com';

# Directories
$app_directory = '/home/klist/test.example.com';

$current_release_directory = $app_directory.'/current';
$shared_directory = $app_directory.'/shared';
$releases_directory = $app_directory.'/releases';

$upcoming_release_directory = $releases_directory.'/'.$release_name;

$symlink_directories = [
    // e.g. <source> => <destination>
    // ln -nfs <source> <destination>

    // magento specific
    $shared_directory.'/pub/media' => $upcoming_release_directory.'/pub/media',
    $shared_directory.'/var/session' => $upcoming_release_directory.'/var/session',
    $shared_directory.'/var/export' => $upcoming_release_directory.'/var/export',
    $shared_directory.'/app/etc/env.php' => $upcoming_release_directory.'/app/etc/env.php',
];
