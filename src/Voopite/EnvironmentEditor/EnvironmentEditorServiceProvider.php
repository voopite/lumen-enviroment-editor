<?php

namespace Voopite\EnvironmentEditor;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Jackiedo\DotenvEditor\Console\Commands\EnvironmentDeleteKeyCommand;
use Jackiedo\DotenvEditor\Console\Commands\EnvironmentGetKeysCommand;
use Jackiedo\DotenvEditor\Console\Commands\EnvironmentSetKeyCommand;

/**
 * EnvironmentEditorServiceProvider.
 *
 * @package Jackiedo\EnvironmentEditor
 *
 * @author Jackie Do <anhvudo@gmail.com>
 */
class EnvironmentEditorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * Loading and publishing package's config.
         */
        $packageConfigPath = __DIR__ . '/Config/config.php';
        $appConfigPath     = config_path('environment-editor.php');

        $this->mergeConfigFrom($packageConfigPath, 'environment-editor');

        $this->publishes([
            $packageConfigPath => $appConfigPath,
        ], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('environment-editor', EnvironmentEditor::class);

        $this->registerCommands();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'environment-editor',
            'command.environment-editor.deletekey',
            'command.environment-editor.getkeys',
            'command.environment-editor.setkey',
        ];
    }

    /**
     * Register commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->app->bind('command.environment-editor.deletekey', EnvironmentDeleteKeyCommand::class);
        $this->app->bind('command.environment-editor.getkeys', EnvironmentGetKeysCommand::class);
        $this->app->bind('command.environment-editor.setkey', EnvironmentSetKeyCommand::class);

        $this->commands('command.environment-editor.deletekey');
        $this->commands('command.environment-editor.getkeys');
        $this->commands('command.environment-editor.setkey');
    }
}
