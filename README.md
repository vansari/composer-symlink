### composer-symlink
Creates Symlinks and can be used with your composer in the scripts section

Installation with composer:

```composer
composer require vansari/composer-symlink
```

#### Usage

All paths must be relative from the composer.json file.

Your Composer JSON:
 ```composer
     {
         "require": {
             "vansari/composer-symlink": "^1.0"
         },
         "scripts": {
            "post-install-cmd": "vansari\\Symlinker::createSymlinks",
            "post-update-cmd: "vansari\\Symlinker::updateSymlinks"
         },
         "extra": {
             "symlinks": {
                 "origin": [
                     "target/subdir"
                 ],
                 "origin2": [
                     "target1/subdir",
                     "target2/subdir"
                 ],
                 "file.php": [
                     ""target3/symlinked.php"
                 ]
             }
         }
     }
 ```
