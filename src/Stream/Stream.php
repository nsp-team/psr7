<?php

namespace NspTeam\Psr7\Stream;

use Exception;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Stream implements StreamInterface
{
    /**
     * Resource modes.
     *
     * @var string
     *
     * @see http://php.net/manual/function.fopen.php
     * @see http://php.net/manual/en/function.gzopen.php
     */
    public const READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';
    public const WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    /**
     * @var resource|null
     */
    protected $stream;
    /**
     * @var int|null bytes
     */
    protected $size;
    /**
     * @var bool|null
     */
    protected $seekable;
    /**
     * @var bool|null
     */
    protected $readable;
    /**
     * @var bool|null
     */
    protected $writable;
    /**
     * @var string|null
     */
    protected $uri;
    /**
     * @var array|null metadata
     */
    protected $customMetadata;

    /**
     * This constructor accepts an associative array of options.
     *
     * - size: (int) If a read stream would otherwise have an indeterminate
     *   size, but the size is known due to foreknowledge, then you can
     *   provide that size, in bytes.
     * - metadata: (array) Any additional metadata to return when the metadata
     *   of the stream is accessed.
     *
     * @param resource $stream Stream resource to wrap.
     * @param array $options Associative array of options.
     *
     */
    public function __construct($stream, array $options = [])
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }
        $this->stream = $stream;

        if (isset($options['size'])) {
            $this->size = $options['size'];
        }

        $this->customMetadata = $options['metadata'] ?? [];

        $meta = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = (bool)preg_match(self::READABLE_MODES, $meta['mode']);
        $this->writable = (bool)preg_match(self::WRITABLE_MODES, $meta['mode']);
        $this->uri = $this->getMetadata('uri');
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function __toString(): string
    {
        try {
            $this->seek(0);
            return (string)stream_get_contents($this->stream);
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * @inheritDoc
     * @return void
     */
    public function close(): void
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * from stream中分割任何隐含的resource。
     * 当这个stream独立后，当前stream 处于不可用状态
     * @inheritDoc
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $result = $this->stream;
        unset($this->stream);
        $this->size = $this->uri = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    /**
     * @inheritDoc
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!isset($this->stream)) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            clearstatcache(true, $this->uri);
        }

        $stats = fstat($this->stream);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }

        return null;
    }

    /**
     * Returns the current position of the file read/write pointer
     * @inheritDoc
     * @return int Position of the file pointer
     */
    public function tell(): int
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        $result = ftell($this->stream);

        if ($result === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        return feof($this->stream);
    }

    /**
     * @inheritDoc
     */
    public function isSeekable()
    {
        return $this->seekable;
    }

    /**
     * @inheritDoc
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @param int $offset Stream offset
     */
    public function seek($offset, $whence = SEEK_SET):void
    {
        $whence = (int)$whence;

        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }
        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable');
        }
        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException(
                'Unable to seek to stream position '
                . $offset . ' with whence ' . var_export($whence, true)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function rewind():void
    {
        $this->seek(0);
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * @inheritDoc
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     */
    public function write($string): int
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }
        if (!$this->writable) {
            throw new RuntimeException('Cannot write to a non-writable stream');
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result = fwrite($this->stream, $string);

        if ($result === false) {
            throw new RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * @inheritDoc
     * @param int $length bytes length
     */
    public function read($length): string
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }
        if (!$this->readable) {
            throw new RuntimeException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new RuntimeException('Length parameter cannot be negative');
        }

        if (0 === $length) {
            return '';
        }

        $string = fread($this->stream, $length);
        if (false === $string) {
            throw new RuntimeException('Unable to read from stream');
        }

        return $string;
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        $contents = stream_get_contents($this->stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    /**
     * @inheritDoc
     * @return mixed|array|null
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            return $key ? null : [];
        }

        if (!$key) {
            // Returns an associative array if no key is provided.
            return array_merge_recursive($this->customMetadata, stream_get_meta_data($this->stream));
        }

        if (isset($this->customMetadata[$key])) {
            // Returns a specific key value if a key is provided and the value is found
            return $this->customMetadata[$key];
        }

        $meta = stream_get_meta_data($this->stream);
        // Returns null if the key is not found
        return $meta[$key] ?? null;
    }
}