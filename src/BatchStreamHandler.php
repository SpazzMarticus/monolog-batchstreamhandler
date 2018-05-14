<?php

namespace SpazzMarticus\Monolog\Handler;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Pushes a batch of records to a stream at once.
 * (Optional) Envelops the records in head and foot lines.
 * You must only use handleBatch on this handler!
 * Suggested usage: BufferHandler flushing to BatchStreamHandler at shutdown
 * Why: Default StreamHandler writes one record at a time, which can be a mess
 *      if there are multiple concurrent calls to your webserver.
 *      If you want all log-records for one call grouped (maybe also enveloped) use this one.
 *
 * (This is an adaption of the default StreamHandler)
 */
class BatchStreamHandler extends AbstractProcessingHandler
{

    /** @var resource|null */
    protected $stream;
    protected $url;

    /** @var string|null */
    private $errorMessage;
    protected $filePermission;
    protected $useLocking;
    private $dirCreated;

    /**
     * (Optional) Head lines
     * @var array
     */
    protected $envelopeHead = array();

    /**
     * (Optional) Foot lines
     * @var array
     */
    protected $envelopeFoot = array();
    protected $bufferedText = '';

    /**
     * @param resource|string $stream
     * @param int             $level          The minimum logging level at which this handler will be triggered
     * @param Boolean         $bubble         Whether the messages that are handled can bubble up the stack or not
     * @param int|null        $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param Boolean         $useLocking     Try to lock log file before doing any writes
     *
     * @throws \Exception                If a missing directory is not buildable
     * @throws \InvalidArgumentException If stream is not a resource or string
     */
    public function __construct($stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false)
    {
        parent::__construct($level, $bubble);
        if (is_resource($stream)) {
            $this->stream = $stream;
        } elseif (is_string($stream)) {
            $this->url = $stream;
        } else {
            throw new \InvalidArgumentException('A stream must either be a resource or a string.');
        }
        $this->filePermission = $filePermission;
        $this->useLocking = $useLocking;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->url && is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    /**
     * Return the currently active stream if it is open
     *
     * @return resource|null
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Return the stream URL if it was configured with a URL and not an active resource
     *
     * @return string|null
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Envelops the given batch of records and calls the next handler
     * @param array $records
     */
    public function handleBatch(array $records)
    {
        foreach ($records as $record) {
            parent::handle($record);
        }

        //Add header/footer only if records are processed, to prevent log containing only header/footer
        if (strlen($this->bufferedText) > 0) {
            $this->bufferedText = implode("\n", $this->envelopeHead) . "\n" . $this->bufferedText . implode("\n", $this->envelopeFoot) . "\n";
        }

        $this->writeToStream();
        $this->bufferedText = '';
    }

    public function handle(array $record)
    {
        throw new \Exception('You must only use handleBatch on this handler!');
    }

    protected function write(array $record)
    {
        $this->bufferedText.=(string) $record['formatted'];
    }

    protected function writeToStream()
    {
        if (strlen($this->bufferedText) > 0) {
            if (!is_resource($this->stream)) {
                if (null === $this->url || '' === $this->url) {
                    throw new \LogicException('Missing stream url, the stream can not be opened.'
                    . ' This may be caused by a premature call to close().');
                }
                $this->createDir();
                $this->errorMessage = null;
                set_error_handler(array($this, 'customErrorHandler'));
                $this->stream = fopen($this->url, 'a');
                if ($this->filePermission !== null) {
                    @chmod($this->url, $this->filePermission);
                }
                restore_error_handler();
                if (!is_resource($this->stream)) {
                    $this->stream = null;
                    throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened: '
                        . $this->errorMessage, $this->url));
                }
            }
            if ($this->useLocking) {
                // ignoring errors here, there's not much we can do about them
                flock($this->stream, LOCK_EX);
            }

            fwrite($this->stream, $this->bufferedText);

            if ($this->useLocking) {
                flock($this->stream, LOCK_UN);
            }
        }
    }

    private function customErrorHandler($code, $msg)
    {
        $this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);
    }

    /**
     * @param string $stream
     *
     * @return null|string
     */
    private function getDirFromStream($stream)
    {
        $pos = strpos($stream, '://');
        if ($pos === false) {
            return dirname($stream);
        }
        if ('file://' === substr($stream, 0, 7)) {
            return dirname(substr($stream, 7));
        }
        return;
    }

    private function createDir()
    {
        // Do not try to create dir if it has already been tried.
        if ($this->dirCreated) {
            return;
        }
        $dir = $this->getDirFromStream($this->url);
        if (null !== $dir && !is_dir($dir)) {
            $this->errorMessage = null;
            set_error_handler(array($this, 'customErrorHandler'));
            $status = mkdir($dir, 0777, true);
            restore_error_handler();
            if (false === $status) {
                throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and its not buildable: ' . $this->errorMessage, $dir));
            }
        }
        $this->dirCreated = true;
    }

    /**
     * Pushes a line to the envelope head
     * @param string $headline
     */
    public function pushHeadLine($headline)
    {
        $this->envelopeHead[] = $headline;
    }

    /**
     * Pushes multiple lines to the envelope head
     * @param array $headlines
     */
    public function pushHeadLines(array $headlines)
    {
        foreach ($headlines as $headline) {
            $this->pushHeadLine($headline);
        }
    }

    /**
     * Pushes a line to the envelope foot
     * @param string $footline
     */
    public function pushFootLine($footline)
    {
        $this->envelopeFoot[] = $footline;
    }

    /**
     * Pushes multiple lines to the envelope foot
     * @param array $footlines
     */
    public function pushFootLines(array $footlines)
    {
        foreach ($footlines as $headline) {
            $this->pushFootLine($headline);
        }
    }
}
