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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:check {symbol} {--d|date= : The date for which the stock price needs to be checked}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the stock price for the specified symbol';

    /**
     * Execute the console command.
     */
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

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
