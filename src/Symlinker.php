<?php
declare (strict_types = 1);

namespace tools;

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copyright 2020.02.07 junker.kurt@gmail.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
 * OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * Class Symlinker - Simple Class to create symlinks via Composer
 * @package tools
 */
class Symlinker {

    /**
     * Creates new Symlinks
     * Attention: Removes any existing symlink which are mapped in the "symlinks"-map before
     *
     * Copy the source to target if the OS is WIN
     * @param Event $event
     */
    public static function createSymlinks(Event $event): void {
        /** @var PackageInterface $package */
        $composer = $event->getComposer();
        $package = $composer->getPackage();
        /** @var Config $config */
        $config = $composer->getConfig();
        $symlinks = (array)$package->getExtra()['symlinks'] ?? [];
        $rootPath = dirname($config->get('vendor-dir'));
        $filesystem = new Filesystem();
        foreach ($symlinks as $root => $destinations) {
            $origin = $rootPath . DIRECTORY_SEPARATOR . $root;
            foreach ($destinations as $destination) {
                $destination = $rootPath . DIRECTORY_SEPARATOR . $destination;
                if ($filesystem->exists($destination)) {
                    echo 'remove symlink from ' . $origin . ' to ' . $destination . PHP_EOL;
                    $filesystem->remove($destination);
                }
                echo 'create symlink from ' . $origin . ' to ' . $destination . PHP_EOL;
                $filesystem->symlink($origin, $destination, true);
            }
        }
    }

    /**
     * Creates new Symlink from the "symlinks"-map which are not existing
     * Copy the source to target if the OS is WIN
     * @param Event $event
     */
    public static function updateSymlinks(Event $event): void {

        /** @var PackageInterface $package */
        $composer = $event->getComposer();
        $package = $composer->getPackage();
        /** @var Config $config */
        $config = $composer->getConfig();
        $symlinks = (array)$package->getExtra()['symlinks'] ?? [];
        $rootPath = dirname($config->get('vendor-dir'));
        $filesystem = new Filesystem();
        foreach ($symlinks as $root => $destinations) {
            $origin = $rootPath . DIRECTORY_SEPARATOR . $root;
            foreach ($destinations as $destination) {
                $destination = $rootPath . DIRECTORY_SEPARATOR . $destination;
                if ($filesystem->exists($destination)) {
                    continue;
                }
                echo 'create missing symlink from ' . $origin . ' to ' . $destination . PHP_EOL;
                $filesystem->symlink($origin, $destination, true);
            }
        }
    }
}