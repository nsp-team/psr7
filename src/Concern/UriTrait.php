<?php

namespace NspTeam\Psr7\Concern;

use Psr\Http\Message\UriInterface;

trait UriTrait
{
    /**
     * Apply parse_url parts to a URI.
     *
     * @param array $parts Array of parse_url parts to apply.
     */
    private function applyParts(array $parts): void
    {
        $this->scheme = isset($parts['scheme'])
            ? $this->filterScheme($parts['scheme'])
            : '';
        $this->userInfo = $parts['user'] ?? '';
        $this->host = isset($parts['host'])
            ? $this->filterHost($parts['host'])
            : '';
        $this->port = isset($parts['port'])
            ? $this->filterPort($parts['port'])
            : null;
        $this->path = isset($parts['path'])
            ? $this->filterPath($parts['path'])
            : '';
        $this->query = isset($parts['query'])
            ? $this->filterQueryAndFragment($parts['query'])
            : '';
        $this->fragment = isset($parts['fragment'])
            ? $this->filterQueryAndFragment($parts['fragment'])
            : '';
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $this->filterUserInfoComponent($parts['pass']);
        }

        $this->removeDefaultPort();
    }

    /**
     * @param string $scheme
     * @return string
     */
    private function filterScheme(string $scheme): string
    {
        return strtolower($scheme);
    }

    /**
     * @param string $host
     * @return string
     */
    private function filterHost(string $host): string
    {
        return strtolower($host);
    }

    /**
     * @param int|null $port
     * @return int|null
     */
    private function filterPort(?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if (1 > $port || 0xffff < $port) {
            throw new \InvalidArgumentException(
                sprintf('Invalid port: %d. Must be between 1 and 65535', $port)
            );
        }
        return $port;
    }

    /**
     * @param string $component
     *
     * @return string
     *
     */
    private function filterUserInfoComponent(string $component): string
    {
        if (!$component) {
            return $component;
        }

        return preg_replace_callback(
            '/(?:[^%' . self::$charUnreserved . self::$charSubDelims . ']+|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawUrlencodeMatchZero'],
            $component
        );
    }

    /**
     * @param UriInterface $uri
     * @param array $keys
     *
     * @return array
     */
    private static function getFilteredQueryString(UriInterface $uri, array $keys): array
    {
        $current = $uri->getQuery();
        if ($current === '') {
            return [];
        }

        $decodedKeys = array_map('rawurldecode', $keys);
        return array_filter(explode('&', $current), static function ($part) use ($decodedKeys) {
            return !in_array(rawurldecode(explode('=', $part)[0]), $decodedKeys, true);
        });
    }

    /**
     * @param string $key
     * @param string|null $value
     *
     * @return string
     */
    private static function generateQueryString(string $key, ?string $value): string
    {
        // Query string separators ("=", "&") within the key or value need to be encoded
        // (while preventing double-encoding) before setting the query string. All other
        // chars that need percent-encoding will be encoded by withQuery().
        $queryString = strtr($key, self::$replaceQuery);
        if ($value !== null) {
            $queryString .= '=' . strtr($value, self::$replaceQuery);
        }

        return $queryString;
    }

    private function removeDefaultPort(): void
    {
        if ($this->port !== null && $this->isDefaultPort()) {
            $this->port = null;
        }
    }

    /**
     * Whether the URI has the default port of the current scheme.
     *
     * `Psr\Http\Message\UriInterface::getPort` may return null or the standard port. This method can be used
     * independently of the implementation.
     *
     * @return bool
     */
    public function isDefaultPort(): bool
    {
        $defaultPort = self::DEFAULT_PORTS[$this->getScheme()] ?? null;
        $isDefaultPort = $this->getPort() === $defaultPort;

        return $this->getPort() === null || $isDefaultPort;
    }

    /**
     * Get default port of the current scheme.
     *
     * @return int
     */
    public function getDefaultPort(): int
    {
        return self::DEFAULT_PORTS[$this->getScheme()] ?? 0;
    }

    /**
     * Filters the path of a URI
     * @param string $path
     * @return string
     */
    private function filterPath(string $path): string
    {
        if (!$path || $path === '/') {
            return $path;
        }

        if (preg_match('#^[\w/-]+$#', $path) === 1) {
            return $path;
        }

        return preg_replace_callback(
            '/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawUrlencodeMatchZero'],
            $path
        );
    }

    /**
     * Filters the query string or fragment of a URI.
     * @param string $str
     * @return string
     */
    private function filterQueryAndFragment(string $str): string
    {
        if (!$str) {
            return $str;
        }

        return preg_replace_callback(
            '/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawUrlencodeMatchZero'],
            $str
        );
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array $match
     * @return string
     */
    private function rawUrlencodeMatchZero(array $match): string
    {
        return rawurlencode($match[0]);
    }

    /**
     * Validate state
     */
    private function validateState(): void
    {
        if ($this->host === '' && ($this->scheme === 'http' || $this->scheme === 'https')) {
            $this->host = self::HTTP_DEFAULT_HOST;
        }

        if ($this->getAuthority() === '') {
            if (0 === strpos($this->path, '//')) {
                throw new \InvalidArgumentException('The path of a URI without an authority must not start with two slashes "//"');
            }
            if ($this->scheme === '' && false !== strpos(explode('/', $this->path, 2)[0], ':')) {
                throw new \InvalidArgumentException('A relative URI must not have a path beginning with a segment containing a colon');
            }
        } elseif (isset($this->path[0]) && $this->path[0] !== '/') {
            @trigger_error(
                'The path of a URI with an authority must start with a slash "/" or be empty. Automagically fixing the URI ' .
                'by adding a leading slash to the path is deprecated since version 1.4 and will throw an exception instead.',
                E_USER_DEPRECATED
            );
            $this->path = '/' . $this->path;
        }
    }

    /**
     * Creates a new URI with a specific query string value.
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair.
     * A value of null will set the query string key without a value, e.g. "key"
     * instead of "key=value".
     *
     * @param UriInterface $uri URI to use as a base.
     * @param string $key Key to set.
     * @param string|null $value Value to set
     *
     * @return UriInterface
     */
    public static function withQueryValue(UriInterface $uri, string $key, ?string $value): UriInterface
    {
        $result = self::getFilteredQueryString($uri, [$key]);
        $result[] = self::generateQueryString($key, $value);

        return $uri->withQuery(implode('&', $result));
    }


}