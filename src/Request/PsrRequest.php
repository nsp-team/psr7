<?php
declare(strict_types=1);

namespace NspTeam\Psr7\Request;

use InvalidArgumentException;
use NspTeam\Psr7\Concern\RequestTrait;
use NspTeam\Psr7\Message\PsrMessage;
use NspTeam\Psr7\Uri\Uri;
use NspTeam\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class PsrRequest extends PsrMessage implements RequestInterface
{
    use RequestTrait;

    /**
     * @var string
     */
    protected $method = 'GET';

    /**
     * @var string
     */
    protected $requestTarget;

    /**
     * @var UriInterface
     */
    protected $uri;

    /**
     * @param string                               $method  HTTP method
     * @param string|UriInterface                  $uri     URI
     * @param array<string, string|string[]>       $headers Request headers
     * @param string|resource|StreamInterface|null $body    Request body
     * @param string                               $version Protocol version
     */
    public function __construct(string $method, $uri, array  $headers = [], $body = null, string $version = '1.1')
    {
        $this->assertMethod($method);
        if (!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }

        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->protocolVersion = $version;
        $this->setHeaders($headers);

        if (!isset($this->headerNames['host'])) {
            $this->updateHostByUri();
        }

        if ($body !== '' && $body !== null) {
            $this->body = Utils::streamFor($body);
        }
    }

    /**
     *  默认为连接`Uri::getQuery()` 和`Uri::getPath()`的值
     * @inheritDoc
     */
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath() ?: '/';
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget($requestTarget)
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target provided; cannot contain whitespace'
            );
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function withMethod($method): RequestInterface
    {
        $this->assertMethod($method);

        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @inheritDoc
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost) {
            $clone->updateHostByUri();
        }

        return $clone;
    }

}