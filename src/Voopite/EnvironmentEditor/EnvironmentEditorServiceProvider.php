<?php

namespace Voopite\EnvironmentEditor;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Voopite\EnvironmentEditor\Console\Commands\EnvironmentDeleteKeyCommand;
use Voopite\EnvironmentEditor\Console\Commands\EnvironmentGetKeysCommand;
use Voopite\EnvironmentEditor\Console\Commands\EnvironmentSetKeyCommand;

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
        $path = 'environment-editor.php';
        $appConfigPath=  $this->app->basePath() . '/config' . ($path ? '/' . $path : $path);


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
}
