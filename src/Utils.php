<?php

namespace NspTeam\Psr7;

use Iterator;
use NspTeam\Psr7\Stream\PumpStream;
use NspTeam\Psr7\Stream\Stream;
use NspTeam\Psr7\Uri\Uri;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Utils
{
    /**
     * Create a new stream based on the input type.
     *
     * Options is an associative array that can contain the following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * @param  resource|string|null|int|float|bool|StreamInterface|callable|Iterator $resource Entity body data
     * @param array $options Additional options
     * @return StreamInterface
     */
    public static function streamFor($resource = '', array $options = []): StreamInterface
    {
        if (is_scalar($resource)) {
            $stream = self::tryFileOpen('php://temp', 'r+');
            if ($resource !== '') {
                fwrite($stream, (string)$resource);
                fseek($stream, 0);
            }
            return new Stream($stream, $options);
        }

        switch (gettype($resource)) {
            case 'resource':
                /*
                 * The 'php://input' is a special stream with quirks and inconsistencies.
                 * We avoid using that stream by reading it into php://temp
                 */
                /** @var resource $resource */
                if ((\stream_get_meta_data($resource)['uri'] ?? '') === 'php://input') {
                    $stream = self::tryFileOpen('php://temp', 'w+');
                    fwrite($stream, stream_get_contents($resource));
                    fseek($stream, 0);
                    $resource = $stream;
                }
                return new Stream($resource, $options);
            case 'object':
                /** @var object $resource */
                if ($resource instanceof StreamInterface) {
                    return $resource;
                }

                if ($resource instanceof \Iterator) {
                    return new PumpStream(function () use ($resource) {
                        if (!$resource->valid()) {
                            return false;
                        }
                        $result = $resource->current();
                        $resource->next();
                        return $result;
                    }, $options);
                }

                if (method_exists($resource, '__toString')) {
                    return self::streamFor((string)$resource, $options);
                }
                break;
            case 'NULL':
                return new Stream(self::tryFileOpen('php://temp', 'r+'), $options);
        }

        if (is_callable($resource)) {
            return new PumpStream($resource, $options);
        }

        throw new \InvalidArgumentException('Invalid resource type: ' . gettype($resource));
    }

    /**
     * Safely opens a PHP stream resource using a filename.
     *
     * When fopen fails, PHP normally raises a warning. This function adds an
     * error handler that checks for errors and throws an exception instead.
     *
     * @param string $filename File to open
     * @param string $mode Mode used to open the file
     * @return resource
     * @throws \RuntimeException if the file cannot be opened
     */
    public static function tryFileOpen(string $filename, string $mode)
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use ($filename, $mode, &$ex): bool {
            $ex = new \RuntimeException(sprintf(
                'Unable to open "%s" using mode "%s": %s',
                $filename,
                $mode,
                $errstr
            ));

            return true;
        });

        try {
            /** @var resource $handle */
            $handle = fopen($filename, $mode);
        } catch (\Throwable $e) {
            $ex = new \RuntimeException(sprintf(
                'Unable to open "%s" using mode "%s": %s',
                $filename,
                $mode,
                $e->getMessage()
            ), 0, $e);
        }

        restore_error_handler();

        if ($ex) {
            throw $ex;
        }

        return $handle;
    }

    /**
     * Returns a UriInterface for the given value.
     *
     * This function accepts a string or UriInterface and returns a
     * UriInterface for the given value. If the value is already a
     * UriInterface, it is returned as-is.
     *
     * @param string|UriInterface $uri
     *
     * @throws \InvalidArgumentException
     */
    public static function uriFor($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if (is_string($uri)) {
            return new Uri($uri);
        }

        throw new \InvalidArgumentException('URI must be a string or UriInterface');
    }
}