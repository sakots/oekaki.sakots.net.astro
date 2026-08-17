<?php
declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'counter.php';

$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$number = is_string($pathInfo) && str_starts_with($pathInfo, '/')
  ? substr($pathInfo, 1)
  : '';

outputNumber($number);
