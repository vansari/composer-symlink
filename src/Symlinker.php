<?php
declare (strict_types = 1);

namespace tools;

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use InvalidArgumentException;
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

    public const RELATIVE_SYMLINK = 'rel';

    public const ABSOLUTE_SYMLINK = 'abs';

    private const ALLOWED_TYPES = [
        self::ABSOLUTE_SYMLINK,
        self::RELATIVE_SYMLINK,
    ];


    /**
     * Creates or replaces symlinks
     * Symlinks must be set in the composer section extra:
     *  "extra": {
     *      "symlinks": {
     *          "sourcepath": {
     *              "rel": [
     *                  "path/to/symlinkrel"
     *              ],
     *              "abs": [
     *                  "path/to/symlinkabs"
     *              ]
     *          }
     *      }
     *  }
     *
     * Attention: Removes any existing symlink which are mapped in the "symlinks"-map before
     * Copy the source to target if the OS is WIN
     *
     * If the parent directory doesn't exist it will be created
     *
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
        foreach ($symlinks as $root => $typeDestinations) {
            $origin = $rootPath . DIRECTORY_SEPARATOR . $root;
            foreach ($typeDestinations as $type => $destinations) {
                self::validType($type);
                foreach ($destinations as $destination) {
                    $destination = $rootPath . DIRECTORY_SEPARATOR . $destination;
                    self::createSymbolicLink($origin, $destination, $type);
                }
            }
        }
    }

    /**
     * Creates the symlink by type and remove existing symlink before
     * @param string $absoluteOrigin - absolute origin path
     * @param string $absoluteDestination - absolute destination path for symlink
     * @param string $type - 'rel' for relative (Not on WIN Sys) or 'abs' for absolute (default)
     */
    public static function createSymbolicLink(
        string $absoluteOrigin,
        string $absoluteDestination,
        string $type = self::ABSOLUTE_SYMLINK
    ): void {
        // Guards
        self::nonEmptyString($absoluteOrigin);
        self::nonEmptyString($absoluteDestination);
        self::validType($type);

        $filesystem = new Filesystem();
        if ($filesystem->exists($absoluteDestination)) {
            echo 'remove symlink from ' . $absoluteOrigin . ' to ' . $absoluteDestination . PHP_EOL;
            $filesystem->remove($absoluteDestination);
        }
        if (
            self::RELATIVE_SYMLINK === $type
            && 'WIN' !== strtoupper(substr(php_uname('a'), 0, 3))
        ) {
            echo 'create relative symlink from ' . $absoluteOrigin . ' to ' . $absoluteDestination . PHP_EOL;
            $newDest = dirname($absoluteDestination);
            $target = basename($absoluteDestination);
            if (!$filesystem->exists($newDest)) {
                $filesystem->mkdir($newDest);
            }
            $absoluteOriginFile = null;
            // if it is a file, remove the filename from $absoluteOrigin,
            // create $relPath and append the filename on the $relPath
            if (is_file($absoluteOrigin)) {
                $absoluteOriginFile = basename($absoluteOrigin);
                $absoluteOrigin = dirname($absoluteOrigin);
            }
            $relPath = $filesystem->makePathRelative($absoluteOrigin, dirname($absoluteDestination));
            $cmd = sprintf(
                'cd %s && ln -s %s %s',
                preg_replace('/\s/', '\\ ', $newDest),
                preg_replace('/\s/', '\\ ', ($relPath . $absoluteOriginFile ?? '')),
                preg_replace('/\s/', '\\ ', $target)
            );
            exec($cmd);
        } else {
            echo 'create absolute symlink from ' . $absoluteOrigin . ' to ' . $absoluteDestination . PHP_EOL;
            $filesystem->symlink($absoluteOrigin, $absoluteDestination);
        }
    }

    /**
     * Guard for a valid symlink type
     * @param string $type
     */
    private static function validType(string $type): void {
        if (in_array($type, self::ALLOWED_TYPES)) {
            return;
        }

        throw new InvalidArgumentException(
            'The type for the symlinkpath is unknown: ' . $type
            . ' (allowed are ' . implode('|', self::ALLOWED_TYPES) . ')');
    }

    private static function nonEmptyString(string $input): void {
        if ('' !== trim($input)) {
            return;
        }

        throw new InvalidArgumentException('The passed variable must be an non empty string');
    }
}
