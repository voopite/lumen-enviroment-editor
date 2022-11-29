<?php

namespace Voopite\EnvironmentEditor\Console\Commands;

use Illuminate\Console\Command;
use Voopite\EnvironmentEditor\Console\Traits\CreateCommandInstanceTrait;
use Symfony\Component\Console\Input\InputOption;

class EnvironmentGetKeysCommand extends Command
{
    use CreateCommandInstanceTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dotenv:get-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all setter in the .env file';

    /**
     * The .env file path.
     *
     * @var null|string
     */
    protected $filePath;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $filePath = $this->stringToType($this->option('filepath'));
        $this->filePath = (is_string($filePath)) ? base_path($filePath) : null;

        $allKeys = $this->editor->load($this->filePath)->getKeys();
        $output = [];

        foreach ($allKeys as $key => $info) {
            $data = [
                'key' => $key,
                'export' => ($info['export']) ? 'true' : 'false',
                'value' => $info['value'],
                'comment' => $info['comment'],
                'line' => $info['line'],
            ];
            $output[] = $data;
        }

        $total = count($output);
        $headers = ['Key', 'Use export', 'Value', 'Comment', 'In line'];

        $this->line('Loading keys in your file...');
        $this->line('');
        $this->table($headers, $output);
        $this->line('');
        $this->info("You have total {$total} keys in your file");
    }

    /**
     * Convert string to corresponding type.
     *
     * @param string $string
     *
     * @return mixed
     */
    protected function stringToType($string)
    {
        if (is_string($string)) {
            switch (true) {
                case 'null' == $string || 'NULL' == $string:
                    $string = null;
                    break;

                case 'true' == $string || 'TRUE' == $string:
                    $string = true;
                    break;

                case 'false' == $string || 'FALSE' == $string:
                    $string = false;
                    break;

                default:
                    break;
            }
        }

        return $string;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['filepath', null, InputOption::VALUE_OPTIONAL, 'The file path should use to load for working. Do not use if you want to load file .env at root application folder.'],
        ];
    }
}
