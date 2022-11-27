<?php

namespace Voopite\EnvironmentEditor\Console\Traits;

use Jackiedo\DotenvEditor\EnvironmentEditor;

trait CreateCommandInstanceTrait
{
    /**
     * The .env file editor instance.
     *
     * @var EnvironmentEditor
     */
    protected $editor;

    /**
     * Create a new command instance.
     */
    public function __construct(EnvironmentEditor $editor)
    {
        parent::__construct();

        $this->editor = $editor;
    }

    /**
     * Execute the console command.
     * This is alias of the method fire().
     *
     * @return mixed
     */
    public function handle()
    {
        return $this->fire();
    }
}
