#!/usr/bin/php
<?php

require __DIR__ . '/../../../../vendor/autoload.php';

use GitHooks\src\CodeQualityTool;

$console = new CodeQualityTool();
$console->run();