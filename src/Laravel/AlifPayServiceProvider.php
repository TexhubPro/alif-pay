<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Laravel;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use TexHub\AlifPay\AlifPay as AlifPayClient;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Webhook\WebhookHandler;

class AlifPayServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/alif-pay.php', 'alif-pay');

        $this->app->singleton(Config::class, function ($app): Config {
            return Config::fromArray((array) $app['config']->get('alif-pay', []));
        });

        $this->app->singleton(AlifPayClient::class, function ($app): AlifPayClient {
            return new AlifPayClient($app->make(Config::class));
        });

        $this->app->alias(AlifPayClient::class, 'alif-pay');

        $this->app->singleton(WebhookHandler::class, function ($app): WebhookHandler {
            return $app->make(AlifPayClient::class)->webhooks();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/alif-pay.php' => $this->app->configPath('alif-pay.php'),
            ], 'alif-pay-config');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            Config::class,
            AlifPayClient::class,
            WebhookHandler::class,
            'alif-pay',
        ];
    }
}
