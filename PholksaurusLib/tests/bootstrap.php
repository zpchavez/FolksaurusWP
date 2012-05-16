<?php
/**
 * Bootstrap file for tests.
 */
require_once '../init.php';
require_once 'PHPUnit/Framework/TestCase.php';

const API_URL = 'http://www.folksaurus.com';
const API_KEY = 'foobarbaz';
define('CONFIG_PATH', __DIR__ . '/configTest.ini');