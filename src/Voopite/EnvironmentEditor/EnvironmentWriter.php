<?php

namespace Voopite\EnvironmentEditor;

use Voopite\EnvironmentEditor\Contracts\FormatterInterface;
use Voopite\EnvironmentEditor\Contracts\WriterInterface;
use Voopite\EnvironmentEditor\Exceptions\UnableWriteToFileException;

/**
 * The EnvironmentWriter writer.
 *
 * @package Jackiedo\EnvironmentEditor
 *
 * @author Jackie Do <anhvudo@gmail.com>
 */
class EnvironmentWriter implements WriterInterface
{
    /**
     * The content buffer.
     *
     * @var array
     */
    protected $buffer;

    /**
     * The instance of Formatter.
     *
     * @var \Voopite\EnvironmentEditor\Workers\Formatters\Formatter
     */
    protected $formatter;

    /**
     * New entry template.
     *
     * @var array
     */
    protected $entryTemplate = [
        'line' => null,
        'type' => 'empty',
        'export' => false,
        'key' => '',
        'value' => '',
        'comment' => '',
    ];

    /**
     * Create a new writer instance.
     */
    public function __construct(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Return content in buffer.
     *
     * @param bool $asArray Use array format for the result
     *
     * @return array|string
     */
    public function getBuffer($asArray = true)
    {
        if ($asArray) {
            return $this->buffer;
        }

        return $this->buildTextContent();
    }

    /**
     * Set buffer with content.
     *
     * @return EnvironmentWriter
     */
    public function setBuffer(array $content = [])
    {
        $this->buffer = $content;

        return $this;
    }

    /**
     * Build plain text content from buffer.
     *
     * @return string
     */
    protected function buildTextContent()
    {
        $data = array_map(function ($entry) {
            if ('setter' == $entry['type']) {
                return $this->formatter->formatSetter($entry['key'], $entry['value'], $entry['comment'], $entry['export']);
            }

            if ('comment' == $entry['type']) {
                return $this->formatter->formatComment($entry['comment']);
            }

            return '';
        }, $this->buffer);

        return implode(PHP_EOL, $data) . PHP_EOL;
    }

    /**
     * Append empty line to buffer.
     *
     * @return EnvironmentWriter
     */
    public function appendEmpty()
    {
        return $this->appendEntry([]);
    }

    /**
     * Append new line to buffer.
     *
     * @return EnvironmentWriter
     */
    protected function appendEntry(array $data = [])
    {
        $this->buffer[] = array_merge($this->entryTemplate, $data);

        return $this;
    }

    /**
     * Append comment line to buffer.
     *
     * @return EnvironmentWriter
     */
    public function appendComment(string $comment)
    {
        return $this->appendEntry([
            'type' => 'comment',
            'comment' => (string)$comment,
        ]);
    }

    /**
     * Append one setter to buffer.
     *
     * @return EnvironmentWriter
     */
    public function appendSetter(string $key, ?string $value = null, ?string $comment = null, bool $export = false)
    {
        return $this->appendEntry([
            'type' => 'setter',
            'export' => $export,
            'key' => (string)$key,
            'value' => (string)$value,
            'comment' => (string)$comment,
        ]);
    }

    /**
     * Update the setter data in buffer.
     *
     * @return EnvironmentWriter
     */
    public function updateSetter(string $key, ?string $value = null, ?string $comment = null, bool $export = false)
    {
        $data = [
            'export' => $export,
            'value' => (string)$value,
            'comment' => (string)$comment,
        ];

        array_walk($this->buffer, function (&$entry, $index) use ($key, $data) {
            if ('setter' == $entry['type'] && $entry['key'] == $key) {
                $entry = array_merge($entry, $data);
            }
        });

        return $this;
    }

    /**
     * Update comment for the setter in buffer.
     *
     * @return EnvironmentWriter
     */
    public function updateSetterComment(string $key, ?string $comment = null)
    {
        $data = [
            'comment' => (string)$comment,
        ];

        array_walk($this->buffer, function (&$entry, $index) use ($key, $data) {
            if ('setter' == $entry['type'] && $entry['key'] == $key) {
                $entry = array_merge($entry, $data);
            }
        });

        return $this;
    }

    /**
     * Update export status for the setter in buffer.
     *
     * @return EnvironmentWriter
     */
    public function updateSetterExport(string $key, bool $state)
    {
        $data = [
            'export' => $state,
        ];

        array_walk($this->buffer, function (&$entry, $index) use ($key, $data) {
            if ('setter' == $entry['type'] && $entry['key'] == $key) {
                $entry = array_merge($entry, $data);
            }
        });

        return $this;
    }

    /**
     * Delete one setter in buffer.
     *
     * @return EnvironmentWriter
     */
    public function deleteSetter(string $key)
    {
        $this->buffer = array_values(array_filter($this->buffer, function ($entry, $index) use ($key) {
            return 'setter' != $entry['type'] || $entry['key'] != $key;
        }, ARRAY_FILTER_USE_BOTH));

        return $this;
    }

    /**
     * Save buffer to special file.
     *
     * @return EnvironmentWriter
     */
    public function saveTo(string $filePath)
    {
        $this->ensureFileIsWritable($filePath);
        file_put_contents($filePath, $this->buildTextContent());

        return $this;
    }

    /**
     * Tests file for writability. If the file doesn't exist, check
     * the parent directory for writability so the file can be created.
     *
     * @param mixed $filePath
     *
     * @return void
     * @throws UnableWriteToFileException
     *
     */
    protected function ensureFileIsWritable($filePath)
    {
        if ((is_file($filePath) && !is_writable($filePath)) || (!is_file($filePath) && !is_writable(dirname($filePath)))) {
            throw new UnableWriteToFileException(sprintf('Unable to write to the file at %s.', $filePath));
        }
    }
}
