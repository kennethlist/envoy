<?php
$servers = [
    'production' => 'deployuser@production.server.net',
    'staging' => 'stage@127.0.0.1',
];

# Variables
$release_name = 'release_' . date('YmdHis');
$releases_to_keep = 5;
$git_repo = 'git@bitbucket.org:magento/example.com-m2.git';

# Magento
$themes = '--theme Magento/luma --theme Magento/backend';
