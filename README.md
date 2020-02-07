### composer-symlink
Creates Symlinks and can be used with your composer in the scripts section<br>


Installation with composer:

```composer
composer require vansari/composer-symlink
```

#### Usage

All paths must be relative from the composer.json file.<br>
Relative paths must be group at property "rel" as array<br>
Absolute paths must be group with property name "abs" as array

Your Composer JSON:
 ```composer
     {
         "require": {
             "vansari/composer-symlink": "^1.0"
         },
         "scripts": {
            "post-install-cmd": "tools\\Symlinker::createSymlinks",
            "post-update-cmd: "tools\\Symlinker::updateSymlinks"
         },
         "extra": {
             "symlinks": {
                 "origin": [
                     "rel": [
                         "target/subdir"
                     ]
                 ],
                 "origin2": [
                     "rel": [
                         "target1/subdir",
                         "target2/subdir"
                     ]
                 ],
                 "file.php": [
                     "abs":[
                         "target3/symlinked.php"
                     ]
                 ]
             }
         }
     }
 ```
