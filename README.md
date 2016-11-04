# SymlinkDetective - real detective for unreal paths

Hey, this library is allows you to find the real path from all that mess, if you use symlinks for some directories in your project.
Just and example:

* Project root dir: */var/www/sites/your-project*
* `/var/www/sites/your-project/library` is pointed to `/var/www/libs/library`
* `/var/www/sites/your-project/public` with app.php inside is pointed to `/var/www/libs/frontend` 
(so `/var/www/sites/your-project/public/app.php` is pointed to `/var/www/libs/frontend/app.php`)

If somewhere in library (`/var/www/libs/library`) you do reference to some path like `library/../app/config.php` 
- your path be equal to `/var/www/libs/library/../app/config` == `/var/www/libs/app/config`, and guess - paths is not exists.

But there is solution - you can call SymlinkDetective::detectPath(__DIR__ . '/../app/config') and mr. SymlinkDetective will do the magic
