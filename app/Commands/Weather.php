<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class Weather extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weather:show';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and display current weather data';

    /**
     * Execute the console command.
     */
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

    /**
     * @param array<string, mixed> $current
     * @param array<string, string> $units
     * @return array{0: array<string>, 1: array<int, array<string, string>>}
     */
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

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
