<?php

/**
 * PurePlate vs Twig: the same page (layout, loop, filters, escaping) rendered by both.
 *
 * Usage: php benchmark/run.php
 * Run without Xdebug, with OPcache enabled, for meaningful numbers.
 */

use Michel\PurePlate\Engine;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/../vendor/autoload.php';

const RENDERS = 2000;
const ROUNDS = 5;

$cacheDir = sys_get_temp_dir() . '/pureplate_benchmark';
$context = ['title' => 'My <Shop>', 'items' => []];
for ($i = 0; $i < 100; $i++) {
    $context['items'][] = (object)['name' => "Item $i & co", 'price' => $i * 10.5, 'stock' => $i % 3];
}

$engines = [
    'PurePlate' => function () use ($cacheDir) {
        $plate = new Engine(__DIR__ . '/templates/pureplate', false, "$cacheDir/pureplate");
        return function (array $context) use ($plate) {
            return $plate->render('page.html', $context);
        };
    },
    'Twig' => function () use ($cacheDir) {
        $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates/twig'), [
            'cache' => "$cacheDir/twig",
            'auto_reload' => false,
        ]);
        return function (array $context) use ($twig) {
            return $twig->render('page.twig', $context);
        };
    },
];

// Both engines must produce the same page, otherwise the comparison means nothing.
$outputs = [];
foreach ($engines as $name => $boot) {
    $outputs[$name] = preg_replace('/\s+/', '', $boot()($context));
}
if (count(array_unique($outputs)) !== 1) {
    exit("The engines do not produce the same HTML, fix the templates first.\n");
}

// OPcache never caches a file written after the current process started, and PurePlate
// includes its cache file on every render: measuring now would be unfair to PurePlate.
foreach (glob("$cacheDir/pureplate/*") ?: [] as $file) {
    if (filemtime($file) >= $_SERVER['REQUEST_TIME'] - 2) {
        exit("Templates just compiled: run the benchmark again to measure with OPcache.\n");
    }
}

printf("PHP %s, OPcache %s, %d renders x best of %d\n\n", PHP_VERSION, ini_get('opcache.enable_cli') ? 'on' : 'off', RENDERS, ROUNDS);
printf("%-10s %18s %22s\n", '', 'render (µs)', 'new engine + render (µs)');

foreach ($engines as $name => $boot) {
    $render = $boot();
    $warm = best(function () use ($render, $context) {
        $render($context);
    });
    $cold = best(function () use ($boot, $context) {
        $boot()($context);
    });
    printf("%-10s %18.1f %22.1f\n", $name, $warm, $cold);
}

function best(callable $fn): float
{
    $best = INF;
    for ($round = 0; $round < ROUNDS; $round++) {
        $start = hrtime(true);
        for ($i = 0; $i < RENDERS; $i++) {
            $fn();
        }
        $best = min($best, (hrtime(true) - $start) / 1000 / RENDERS);
    }
    return $best;
}
