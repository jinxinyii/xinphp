<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'xinPHP';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f5c46">
    <link rel="stylesheet" href="assets/css/output.css">
    <title><?= e($pageTitle) ?></title>
</head>
