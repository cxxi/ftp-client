<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$_ENV['SFTP_HOST'] ??= '127.0.0.1';
$_ENV['SFTP_PORT'] ??= '2222';
$_ENV['SFTP_USER'] ??= 'test';
$_ENV['SFTP_PASS'] ??= 'test';

$_ENV['FTP_HOST'] ??= '127.0.0.1';
$_ENV['FTP_PORT'] ??= '2121';
$_ENV['FTP_USER'] ??= 'test';
$_ENV['FTP_PASS'] ??= 'test';

$_ENV['FTPS_HOST'] ??= '127.0.0.1';
$_ENV['FTPS_PORT'] ??= '2990';
$_ENV['FTPS_USER'] ??= 'test';
$_ENV['FTPS_PASS'] ??= 'test';
