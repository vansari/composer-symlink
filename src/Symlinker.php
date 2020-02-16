<?php
declare (strict_types = 1);

namespace tools;

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copyright 2020-02-07 junker.kurt@gmail.com
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
     * Symlinks must be set in the composer section extra. Root of target and source path is
     * the parent directory of the vendor folder.
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
            // 1. create the full path of the origin
            $origin = $rootPath . DIRECTORY_SEPARATOR . $root;
            foreach ($typeDestinations as $type => $destinations) {
                self::validType($type);
                foreach ($destinations as $destination) {
                    // 2. Create the full path of the destination
                    $destination = $rootPath . DIRECTORY_SEPARATOR . $destination;
                    self::createSymbolicLink($origin, $destination, $type);
                }
            }
        }
    }

    /**
     * Creates the symlink by type and remove existing symlink before
     * The difference is that you can use any existing Path for Target and source
     * the method will find the closest path from destination to origin by fetching the
     * parent paths from destination and compare it with the origin
     *
     * origin:      /foo/bar/baz/fooz/barz/bazz
     *                |   |   |   ┌--------┘
     *                |   |   |   └1---2---┐
     * destination: /foo/bar/baz/boo/bar/fooz
     *
     *         destination -->  1/ 2/<dir>/<dir>/origin
     * result:        fooz --> ../../fooz/barz/bazz
     *
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

        if (self::RELATIVE_SYMLINK === $type && false === self::isWindows()) {
            self::createSymbolicLinkRelative($absoluteOrigin, $absoluteDestination);
        } else {
            self::createSymbolicLinkAbsolute($absoluteOrigin, $absoluteDestination);
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

    /**
     * Ensure that the given string is not empty
     * @param string $input
     */
    private static function nonEmptyString(string $input): void {
        if ('' !== trim($input)) {
            return;
        }

        throw new InvalidArgumentException('The passed variable must be an non empty string');
    }

    /**
     * @return bool
     */
    public static function isWindows(): bool
    {
        return 'WIN' === strtoupper(substr(php_uname('a'), 0, 3));
    }

    /**
     * @param string $absoluteOrigin
     * @param string $absoluteDestination
     */
    private static function createSymbolicLinkRelative(string $absoluteOrigin, string $absoluteDestination): void
    {
        $filesystem = new Filesystem();
        echo 'create relative symlink from ' . $absoluteOrigin . ' to ' . $absoluteDestination . PHP_EOL;
        $absoluteOriginFile = null;
        // if it is a file, remove the filename from $absoluteOrigin,
        // create $relPath and append the filename on the $relPath
        if (is_file($absoluteOrigin)) {
            $absoluteOriginFile = basename($absoluteOrigin);
            $absoluteOrigin = dirname($absoluteOrigin);
        }
        $relPath = $filesystem->makePathRelative($absoluteOrigin, dirname($absoluteDestination));
        // Remove trailing slash if $absoluteOriginFile is null
        $modifiedOrigin = (null === $absoluteOriginFile ? rtrim($relPath, '/\\') : $relPath)
            . $absoluteOriginFile ?? '';

        $filesystem->symlink(
            $modifiedOrigin,
            $absoluteDestination
        );
    }

    /**
     * @param string $absoluteOrigin
     * @param string $absoluteDestination
     */
    private static function createSymbolicLinkAbsolute(string $absoluteOrigin, string $absoluteDestination): void
    {
        $filesystem = new Filesystem();
        echo 'create absolute symlink from ' . $absoluteOrigin . ' to ' . $absoluteDestination . PHP_EOL;
        try {
            // @link https://www.php.net/manual/en/function.symlink.php#refsect1-function.symlink-changelog
            // try to create symlink also on Win System and if it fails, try to copy the files
            $filesystem->symlink($absoluteOrigin, $absoluteDestination);
        } catch (\Throwable $exception) {
            echo 'could not create absolute symlink. Message is: ' . $exception->getMessage();
            if (self::isWindows()) {
                echo 'WIN System: try to copy origin to destination...' . PHP_EOL;
                // Filesystem::mirror doesn't handle files as parameter,
                // it will throw UnexpectedValueException : RecursiveDirectoryIterator::__construct
                // So we will remove the existing destination file and copy the origin file to destination
                if (is_file($absoluteOrigin)) {
                    $filesystem->copy($absoluteOrigin, $absoluteDestination, true);
                    return;
                }
                $filesystem->symlink($absoluteOrigin, $absoluteDestination, true);
            }
        }
    }
}
