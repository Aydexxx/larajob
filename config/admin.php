<?php

/*
|--------------------------------------------------------------------------
| Admin Seed Credentials
|--------------------------------------------------------------------------
|
| Read here (not directly via env() in the seeder) so these still work if
| the deployment ever runs `php artisan config:cache` — env() calls outside
| config files return null once the config is cached.
|
| Leave ADMIN_PASSWORD unset in local/testing to use the documented demo
| password. Outside those environments, an unset ADMIN_PASSWORD makes
| AdminSeeder generate and print a random one instead of ever falling back
| to a guessable default — see database/seeders/AdminSeeder.php.
|
*/

return [
    'email' => env('ADMIN_EMAIL', 'admin@larajob.test'),
    'password' => env('ADMIN_PASSWORD'),
];
