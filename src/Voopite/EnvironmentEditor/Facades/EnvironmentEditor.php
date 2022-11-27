<?php

namespace Voopite\EnvironmentEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The EnvironmentEditor facade.
 *
 * @package Jackiedo\EnvironmentEditor\Facades
 *
 * @author Jackie Do <anhvudo@gmail.com>
 */
class EnvironmentEditor extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'environment-editor';
    }
}
