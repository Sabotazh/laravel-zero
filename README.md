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

## Weather

```bash
php spark app:rename weather
```

```bash
php weather app:install http
```

```bash
php weather make:command Weather
```
```php
<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class Weather extends Command
{
    protected $signature = 'weather:show';

    public function handle(): int
    {
        try {
            $currentWeather = Http::timeout(5)
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude'         => 49.43,
                    'longitude'        => 24.93,
                    'current'          => 'temperature_2m,weather_code,cloud_cover,precipitation,wind_speed_10m,wind_gusts_10m',
                    'wind_speed_unit'  => 'ms',
                    'timezone'         => 'auto',
                ])
                ->throw()
                ->json('current');

            if (! is_array($currentWeather)) {
                $this->error('Invalid payload structure: missing "current" key.');
                return self::FAILURE;
            }

            [$headers, $rows] = $this->getTablePayload($currentWeather);

            $this->info("Hello Artisan! Today's weather:");
            $this->table($headers, $rows);

            if (method_exists($this, 'notify')) {
                $this->notify('Weather info!', 'Weather information just arrived!');
            }

            return self::SUCCESS;
        } catch (RequestException $e) {
            $this->error("HTTP request failed: {$e->response->status()} - {$e->getMessage()}");
            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Network error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    public function getTablePayload(array $current, array $units = []): array
    {
        $headers = ['Information', 'Value'];
        $excludedKeys = ['interval'];

        $rows = collect($current)
            ->except($excludedKeys)
            ->map(function ($value, $key) use ($units) {
                $formattedValue = match ($key) {
                    'weather_code' => sprintf('%d (%s)', $value, $this->describeWeatherCode((int) $value)),
                    'time' => (string) str($value)->replace('T', ' '),
                    default => trim("$value " . ($units[$key] ?? '')),
                };

                return [
                    'Information' => $this->formatMetricName($key),
                    'Value' => $formattedValue,
                ];
            })
            ->values()
            ->all();

        return [$headers, $rows];
    }

    protected function formatMetricName(string $key): string
    {
        return match ($key) {
            'time'            => 'Observation Time',
            'temperature_2m'  => 'Temperature (2m)',
            'weather_code'    => 'Weather Condition',
            'cloud_cover'     => 'Cloud Cover',
            'precipitation'   => 'Precipitation',
            'wind_speed_10m'  => 'Wind Speed (10m)',
            'wind_gusts_10m'  => 'Wind Gusts (10m)',
            default           => (string) str($key)->headline(),
        };
    }

    protected function describeWeatherCode(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            $code === 1 => 'Mainly clear',
            $code === 2 => 'Partly cloudy',
            $code === 3 => 'Overcast',
            in_array($code, [45, 48], true) => 'Fog',
            $code >= 51 && $code <= 57 => 'Drizzle',
            $code === 65 || $code === 67 => 'Heavy rain',
            $code >= 61 && $code <= 67 => 'Rain',
            $code >= 71 && $code <= 77 => 'Snow',
            $code >= 80 && $code <= 82 => 'Rain showers',
            $code === 96 || $code === 99 => 'Thunderstorm with hail',
            $code >= 95 && $code <= 99 => 'Thunderstorm',
            default => 'Unknown',
        };
    }
}
```

```bash
php weather weather:show
```

## Stock price check

```bash
php weather app:rename artisan
```

```bash
php artisan app:install dotenv
```

```bash
echo -e "<?php\n\nreturn [\n\t'api_key' => env('POLYGON_API_KEY'),\n];" > config/polygon.php
```

```bash
php artisan make:command CheckStock
```

```php
<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class CheckStock extends Command
{
    protected $signature = 'stock:check {symbol} {--d|date= : The date for which the stock price needs to be checked}';
    protected $description = 'Check the stock price for the specified symbol';

    public function handle(): void
    {
        $symbol = Str::upper($this->argument('symbol'));
        $date = now()->previousWeekday();

        if ($dateOption = $this->option('date')) {
            $date = Carbon::parse($dateOption);
            if ($date->isToday() || $date->isFuture()) {
                $this->error('The date must be in the past.');
                return;
            }
        }

        if ($date->lt(now()->subYear())) {
            $this->error('The date must be within the last year.');
            return;
        }

        $ticker = $this->getClient()
            ->withUrlParameters(['symbol' => $symbol])
            ->withQueryParameters(['date' => $date->toDateString()])
            ->throw()
            ->get("https://api.polygon.io/v3/reference/tickers/{symbol}")
            ->json('results');

        $openClose = $this->getClient()
            ->withUrlParameters([
                'symbol' => $symbol,
                'date' => $date->toDateString()
            ])
            ->get("https://api.polygon.io/v1/open-close/{symbol}/{date}?adjusted=true");

        if ($openClose->failed()) {
            $this->error("Failed to retrieve stock data.\nStatus: " . $openClose->json('status') . "\nMessage: " . $openClose->json('message') . "\n");
            return;
        }


        $this->info("Stock: {$ticker['name']} ({$ticker['ticker']})");
        $this->info("Date: {$date->toDateString()}");
        $this->info("Currency: {$ticker['currency_name']}");
        $this->table(['Open', 'Close', 'High', 'Low'], [
            [
                number_format($openClose['open'], 2),
                number_format($openClose['close'], 2),
                number_format($openClose['high'], 2),
                number_format($openClose['low'], 2),
            ],
        ]);
    }

    protected function getClient(): PendingRequest
    {
        return Http::withToken(config('polygon.api_key'));
    }
}
```

```bash
php artisan stock:check AAPL --date=2026-10-01
```

## License

Licensed under the MIT license.
