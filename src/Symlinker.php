<?php
declare (strict_types = 1);

namespace tools;

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
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