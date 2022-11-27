<?php

namespace Voopite\EnvironmentEditor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Jackiedo\DotenvEditor\Exceptions\FileNotFoundException;
use Jackiedo\DotenvEditor\Exceptions\KeyNotFoundException;
use Jackiedo\DotenvEditor\Exceptions\NoBackupAvailableException;
use Jackiedo\DotenvEditor\Workers\Formatters\Formatter;
use Jackiedo\DotenvEditor\Workers\Parsers\ParserV1;
use Jackiedo\DotenvEditor\Workers\Parsers\ParserV2;
use Jackiedo\DotenvEditor\Workers\Parsers\ParserV3;
use Jackiedo\PathHelper\Path;

/**
 * The EnvironmentEditor class.
 *
 * @package Jackiedo\EnvironmentEditor
 *
 * @author Jackie Do <anhvudo@gmail.com>
 */
class EnvironmentEditor
{
    /**
     * The IoC Container.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Store instance of Config Repository;.
     *
     * @var Config
     */
    protected $config;

    /**
     * Compatible parser map.
     *
     * This map allowed select the reader parser compatible with
     * the "vlucas/phpdotenv" package based on its version
     *
     * @var array
     */
    protected $combatibleParserMap = [
        '5.0.0' => ParserV3::class,  // Laravel 8.x|9.x using "vlucas/dotenv" ^v5.0|^5.4
        '4.0.0' => ParserV2::class,  // Laravel 7.x using "vlucas/dotenv" ^v4.0
        '3.3.0' => ParserV1::class,  // Laravel 5.8 and 6.x using "vlucas/dotenv" ^v3.3
    ];

    /**
     * The reader instance.
     *
     * @var EnvironmentReader
     */
    protected $reader;

    /**
     * The writer instance.
     *
     * @var EnvironmentWriter
     */
    protected $writer;

    /**
     * The file path.
     *
     * @var string
     */
    protected $filePath;


    /**
     * The changed state of buffer.
     *
     * @var bool
     */
    protected $hasChanged;

    /**
     * Create a new EnvironmentEditor instance.
     *
     * @return void
     */
    public function __construct(Container $app, Config $config)
    {
        $this->app    = $app;
        $this->config = $config;

        $parser       = $this->selectCompatibleParser();
        $this->reader = new EnvironmentReader(new $parser);
        $this->writer = new EnvironmentWriter(new Formatter);

        $this->load();
    }

    /**
     * Load file for working.
     *
     * @param null|string $filePath          The file path
     * @param bool        $restoreIfNotFound Restore this file from other file if it's not found
     * @param null|string $restorePath       The file path you want to restore from
     *
     * @return EnvironmentEditor
     */
    public function load(?string $filePath = null, bool $restoreIfNotFound = false, ?string $restorePath = null)
    {
        $this->init();

        $this->filePath = $this->standardizeFilePath($filePath);

        $this->reader->load($this->filePath);

        if (file_exists((string) $this->filePath)) {
            $this->buildBuffer();

            return $this;
        }

        if ($restoreIfNotFound) {
            return $this->restore($restorePath);
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Working with reading
    |--------------------------------------------------------------------------
    |
    | getContent()
    | getEntries()
    | getKey()
    | getKeys()
    | keyExists()
    | getValue()
    |
    */

    /**
     * Get raw content of file.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->reader->content();
    }

    /**
     * Get all entries from file.
     *
     * @return array
     */
    public function getEntries(bool $withParsedData = false)
    {
        return $this->reader->entries($withParsedData);
    }

    /**
     * Get all or exists given keys in file content.
     *
     * @return array
     */
    public function getKeys(array $keys = [])
    {
        $allKeys = $this->reader->keys();

        if (empty($keys)) {
            return $allKeys;
        }

        return array_filter($allKeys, function ($key) use ($keys) {
            return in_array($key, $keys);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Return information of entry matching to a given key in the file content.
     *
     * @throws KeyNotFoundException
     *
     * @return array
     */
    public function getKey(string $key)
    {
        $allKeys = $this->getKeys([$key]);

        if (array_key_exists($key, $allKeys)) {
            return $allKeys[$key];
        }

        throw new KeyNotFoundException('Requested key not found in your environment file.');
    }

    /**
     * Return the value matching to a given key in the file content.
     *
     * @return string
     */
    public function getValue(string $key)
    {
        return $this->getKey($key)['value'];
    }

    /**
     * Check, if a given key is exists in the file content.
     *
     * @param string $keys
     *
     * @return bool
     */
    public function keyExists(string $key)
    {
        $allKeys = $this->getKeys();

        return array_key_exists($key, $allKeys);
    }

    /*
    |--------------------------------------------------------------------------
    | Working with writing
    |--------------------------------------------------------------------------
    |
    | hasChange()
    | getBuffer()
    | addEmpty()
    | addComment()
    | setKeys()
    | setKey()
    | setSetterComment()
    | clearSetterComment()
    | setExportSetter()
    | deleteKeys()
    | deleteKey()
    | save()
    |
    */

    /**
     * Determine if the buffer has changed.
     *
     * @return bool
     */
    public function hasChanged()
    {
        return $this->hasChanged;
    }

    /**
     * Return content in buffer.
     *
     * @param bool $asArray Use array format for the result
     *
     * @return array
     */
    public function getBuffer(bool $asArray = true)
    {
        return $this->writer->getBuffer($asArray);
    }

    /**
     * Add empty line to buffer.
     *
     * @return EnvironmentEditor
     */
    public function addEmpty()
    {
        $this->writer->appendEmpty();

        $this->hasChanged = true;

        return $this;
    }

    /**
     * Add comment line to buffer.
     *
     * @return EnvironmentEditor
     */
    public function addComment(string $comment)
    {
        $this->writer->appendComment($comment);

        $this->hasChanged = true;

        return $this;
    }

    /**
     * Set many keys to buffer.
     *
     * @return EnvironmentEditor
     */
    public function setKeys(array $data)
    {
        foreach ($data as $index => $setter) {
            if (!is_array($setter)) {
                if (!is_string($index)) {
                    continue;
                }

                $setter = [
                    'key'   => $index,
                    'value' => $setter,
                ];
            }

            if (array_key_exists('key', $setter)) {
                $key     = (string) $setter['key'];
                $value   = (string) array_key_exists('value', $setter) ? $setter['value'] : null;
                $comment = array_key_exists('comment', $setter) ? $setter['comment'] : null;
                $export  = array_key_exists('export', $setter) ? $setter['export'] : null;

                if (!is_file($this->filePath) || !$this->keyExists($key)) {
                    $this->writer->appendSetter($key, $value, (string) $comment, (bool) $export);
                } else {
                    $oldInfo = $this->getKeys([$key]);
                    $comment = is_null($comment) ? $oldInfo[$key]['comment'] : (string) $comment;
                    $export  = is_null($export) ? $oldInfo[$key]['export'] : (bool) $export;

                    $this->writer->updateSetter($key, $value, $comment, $export);
                }

                $this->hasChanged = true;
            }
        }

        return $this;
    }

    /**
     * Set one key to|in the buffer.
     *
     * @param string      $key     Key name of setter
     * @param null|string $value   Value of setter
     * @param null|string $comment Comment of setter
     * @param null|bool   $export  Leading key name by "export "
     *
     * @return EnvironmentEditor
     */
    public function setKey(string $key, ?string $value = null, ?string $comment = null, $export = null)
    {
        $data = [compact('key', 'value', 'comment', 'export')];

        return $this->setKeys($data);
    }

    /**
     * Set the comment for setter.
     *
     * @param string      $key     Key name of setter
     * @param null|string $comment The comment content
     *
     * @return EnvironmentEditor
     */
    public function setSetterComment(string $key, ?string $comment = null)
    {
        $this->writer->updateSetterComment($key, $comment);

        $this->hasChanged = true;

        return $this;
    }

    /**
     * Clear the comment for setter.
     *
     * @param string $key Key name of setter
     *
     * @return EnvironmentEditor
     */
    public function clearSetterComment(string $key)
    {
        return $this->setSetterComment($key, null);
    }

    /**
     * Set the export status for setter.
     *
     * @param string $key   Key name of setter
     * @param bool   $state Leading key name by "export "
     *
     * @return EnvironmentEditor
     */
    public function setExportSetter(string $key, bool $state = true)
    {
        $this->writer->updateSetterExport($key, $state);

        $this->hasChanged = true;

        return $this;
    }

    /**
     * Delete many keys in buffer.
     *
     * @return EnvironmentEditor
     */
    public function deleteKeys(array $keys = [])
    {
        foreach ($keys as $key) {
            $this->writer->deleteSetter($key);
        }

        $this->hasChanged = true;

        return $this;
    }

    /**
     * Delete on key in buffer.
     *
     * @return EnvironmentEditor
     */
    public function deleteKey(string $key)
    {
        $keys = [$key];

        return $this->deleteKeys($keys);
    }

    /**
     * Save buffer to file.
     *
     * @param bool $rebuildBuffer Rebuild buffer from content of dotenv file
     *
     * @return EnvironmentEditor
     */
    public function save(bool $rebuildBuffer = true)
    {

        $this->writer->saveTo($this->filePath);

        if ($rebuildBuffer && $this->hasChanged()) {
            $this->buildBuffer();
        }

        return $this;
    }



    /**
     * Initialize content for editor.
     *
     * @return void
     */
    protected function init()
    {
        $this->hasChanged = false;
        $this->filePath = null;

        $this->reader->load(null);
        $this->writer->setBuffer([]);
    }

    /**
     * Standardize the file path.
     *
     * @return string
     */
    protected function standardizeFilePath(?string $filePath = null)
    {
        if (is_null($filePath)) {
            if (method_exists($this->app, 'environmentPath') && method_exists($this->app, 'environmentFile')) {
                $filePath = Path::osStyle($this->app->environmentPath() . '/' . $this->app->environmentFile());
            } else {
                $filePath = Path::osStyle($this->app->basePath() . '/' . '.env');
            }
        }

        return $filePath;
    }

    /**
     * Build buffer for writer.
     *
     * @return void
     */
    protected function buildBuffer()
    {
        $entries = $this->getEntries(true);

        $buffer = array_map(function ($entry) {
            $data = [
                'line' => $entry['line'],
            ];

            return array_merge($data, $entry['parsed_data']);
        }, $entries);

        $this->writer->setBuffer($buffer);

        $this->hasChanged = false;
    }

    /**
     * Create backup folder if not exists.
     *
     * @return void
     */
    protected function createBackupFolder()
    {
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0777, true);
        }
    }

    /**
     * Select the parser compatible with the "vlucas/phpdotenv" package.
     *
     * @return string
     */
    protected function selectCompatibleParser()
    {
        $installedDotenvVersion = $this->getDotenvPackageVersion();

        uksort($this->combatibleParserMap, function ($front, $behind) {
            return version_compare($behind, $front);
        });

        foreach ($this->combatibleParserMap as $minRequiredVersion => $compatibleParser) {
            if (version_compare($installedDotenvVersion, $minRequiredVersion) >= 0) {
                return $compatibleParser;
            }
        }

        return ParserV1::class;
    }

    /**
     * Catch version of the "vlucas/phpdotenv" package.
     *
     * @return string
     */
    protected function getDotenvPackageVersion()
    {
        $composerLock  = $this->app->basePath() . DIRECTORY_SEPARATOR . 'composer.lock';
        $arrayContent  = json_decode(file_get_contents($composerLock), true);
        $dotenvPackage = array_values(array_filter($arrayContent['packages'], function ($packageInfo, $index) {
            return 'vlucas/phpdotenv' === $packageInfo['name'];
        }, ARRAY_FILTER_USE_BOTH))[0];

        return preg_replace('/[a-zA-Z]/', '', $dotenvPackage['version']);
    }
}
