<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Claude\AnthropicClaudeClient;
use App\Services\Claude\ClaudeClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the {@see ClaudeClient} seam to its Anthropic-backed implementation.
 *
 * Tests swap this binding for a fake (`$this->app->instance(ClaudeClient::class, ...)`)
 * so the suite never reaches the network.
 */
final class ClaudeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClaudeClient::class, function (Application $app): AnthropicClaudeClient {
            $config = $app['config']->get('services.anthropic', []);

            return new AnthropicClaudeClient(
                apiKey: (string) ($config['key'] ?? ''),
                model: (string) ($config['model'] ?? 'claude-opus-4-8'),
            );
        });
    }
}
