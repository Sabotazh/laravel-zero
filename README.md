# Laravel Zero

My attempts to master this framework.

## Hello world

```bash
composer create-project --prefer-dist laravel-zero/laravel-zero hello-world
```

```bash
php application app:rename spark
```

```bash
php spark make:command HelloWorld
```

```php
<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class HelloWorld extends Command
{
    protected $signature = 'hello:world';

    public function handle(): void
    {
        $this->info('Hello, Laravel Zero!');
    }
}
```

```bash
php spark hello:world
```

## License

Licensed under the MIT license.
