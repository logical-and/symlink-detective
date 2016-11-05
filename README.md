# SymlinkDetective - real detective for unreal paths

[![Latest Stable Version](https://poser.pugx.org/and/symlink-detective/v/stable)](https://packagist.org/packages/and/symlink-detective)
[![Latest Unstable Version](https://poser.pugx.org/and/symlink-detective/v/unstable)](https://packagist.org/packages/and/symlink-detective)
[![License](https://poser.pugx.org/and/symlink-detective/license)](https://packagist.org/packages/and/symlink-detective)
[![composer.lock](https://poser.pugx.org/and/symlink-detective/composerlock)](https://packagist.org/packages/and/symlink-detective)

Hey, this library is allows you to find the real path from all that mess, if you use symlinks for some directories in your project.
Just and example:

* Project root dir: `/var/www/sites/your-project`
* `/var/www/sites/your-project/library` is pointed to `/var/www/libs/library`
* `/var/www/sites/your-project/public` with app.php inside is pointed to `/var/www/libs/frontend` 
(so `/var/www/sites/your-project/public/app.php` is pointed to `/var/www/libs/frontend/app.php`)

If somewhere in library (`/var/www/libs/library`) you do reference to some path like `library/../app/config.php` 
- your path be equal to `/var/www/libs/library/../app/config` == `/var/www/libs/app/config`, and guess - paths is not exists.

But there is solution - you can call `SymlinkDetective::detectPath(__DIR__ . '/../app/config')` and mr. SymlinkDetective will do the magic

Examples

* `SymlinkDetective::detectPath(__DIR__ . '/../app/config')` returns `/var/www/sites/your-project/app/config`
* `SymlinkDetective::detectPath(__FILE__,  '/../app/config')` returns `/var/www/sites/your-project/app/config`
* `SymlinkDetective::detectPath(__FILE__,  '/../app/unexistent-file', false)` throws an Exception as file doesn't found/exists
* `SymlinkDetective::detectPath(__DIR__ . '/../unexistent-file')` returns `/var/www/libs/library/unexistent-file` as file not found and Exception throwing is muted (3rd argument)
