<?php
declare(strict_types=1);

namespace NspTeam\Psr7\Message;

use NspTeam\Psr7\Concern\MessageTrait;
use NspTeam\Psr7\Utils;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class PsrMessage implements MessageInterface
{
    use MessageTrait;

    /**
     * @var string
     */
    protected $protocolVersion = '1.1';

    /**
     * @var StreamInterface
     */
    protected $body;

    /**
     * Map of all registered headers, as original name => array of values
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Map of lowercase header name => original name at registration
     *
     * @var array
     */
    protected $headerNames = [];

    /**
     * @inheritDoc
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion ?: '1.1';
    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion($version): MessageInterface
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $clone = clone $this;

        $clone->protocolVersion = $version;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * @inheritDoc
     */
    public function getHeader($name): array
    {
        $headerName = strtolower($name);
        if (!isset($this->headerNames[$headerName])) {
            return [];
        }

        $header = $this->headerNames[$headerName];

        return $this->headers[$header];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @inheritDoc
     */
    public function withHeader($name, $value): MessageInterface
    {
        $this->assertHeader($name);
        $headerName = strtolower($name);
        $newValue = $this->trimHeaderValues((array)$value);

        $clone = clone $this;

        if (isset($clone->headerNames[$headerName])) {
            unset($clone->headers[$clone->headerNames[$headerName]]);
        }

        $clone->headerNames[$headerName] = $name;
        $clone->headers[$name] = $newValue;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader($name, $value): MessageInterface
    {
        $this->assertHeader($name);
        $headerName = strtolower($name);
        $newValue = $this->trimHeaderValues((array)$value);

        $clone = clone $this;

        if (isset($clone->headerNames[$headerName])) {
            $header = $clone->headerNames[$headerName];
            $clone->headers[$header] = array_merge($this->headers[$header], $newValue);
        } else {
            $clone->headerNames[$headerName] = $name;
            $clone->headers[$name] = $newValue;
        }

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader($name): MessageInterface
    {
        $headerName = strtolower($name);
        if (!isset($this->headerNames[$headerName])) {
            return $this;
        }

        $rawName = $this->headerNames[$headerName];

        $clone = clone $this;
        unset($clone->headers[$rawName], $clone->headerNames[$headerName]);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getBody(): StreamInterface
    {
        if (!$this->body) {
            $this->body = Utils::streamFor();
        }

        return $this->body;
    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->body) {
            return $this;
        }

        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * @param array<string|int, string|string[]> $headers
     * @return static
     */
    public function withHeaders(array $headers): MessageInterface
    {
        $clone = clone $this;

        foreach ($headers as $header => $value) {
            $this->assertHeader($header);
            $headerName = strtolower((string)$header);
            $value = $clone->trimHeaderValues((array)$value);

            if (isset($clone->headerNames[$headerName])) {
                $headerBefore = $clone->headerNames[$headerName];
                // re-save
                $clone->headers[$headerBefore] = array_merge($clone->headers[$headerBefore], $value);
                continue;
            }

            $clone->headerNames[$headerName] = $header;
            $clone->headers[$header] = $value;
        }

        return $clone;
    }

    /**
     * @param array<string|int, string|string[]> $headers
     */
    protected function setHeaders(array $headers): void
    {
        $this->headerNames = $this->headers = [];

        foreach ($headers as $header => $value) {
            $this->assertHeader($header);
            $headerName = strtolower((string)$header);
            $value = $this->trimHeaderValues((array)$value);

            if (isset($this->headerNames[$headerName])) {
                $headerBefore = $this->headerNames[$headerName];
                $this->headers[$headerBefore] = array_merge($this->headers[$headerBefore], $value);
            } else {
                $this->headerNames[$headerName] = $header;
                $this->headers[$header] = $value;
            }
        }
    }
}