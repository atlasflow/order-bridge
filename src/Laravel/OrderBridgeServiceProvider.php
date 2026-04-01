<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Laravel;

use Atlasflow\OrderBridge\SchemaVersion;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel service provider for the Atlas Core Order Bridge package.
 *
 * Registers the package configuration and applies any published overrides to
 * {@see SchemaVersion} so that all arithmetic throughout the package respects
 * the values set in config/order-bridge.php.
 *
 * Usage — in config/app.php (or auto-discovered via package discovery):
 *
 *   'providers' => [
 *       Atlasflow\OrderBridge\Laravel\OrderBridgeServiceProvider::class,
 *   ],
 *
 * Publish the configuration file with:
 *
 *   php artisan vendor:publish --tag=order-bridge-config
 */
class OrderBridgeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services.
     *
     * Merges the package defaults so that the config values are always present
     * even if the application has not published or modified the config file.
     * Then applies any overrides to the runtime configuration registry.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/order-bridge.php' => config_path('order-bridge.php'),
        ], 'order-bridge-config');
    }

    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/order-bridge.php',
            'order-bridge',
        );

        SchemaVersion::configure(config('order-bridge', []));
    }
}
