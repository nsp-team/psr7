<?php
declare(strict_types=1);

namespace NspTeam\Psr7\Concern;

use InvalidArgumentException;

trait RequestTrait
{
    /**
     * Update Host Header according to Uri
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.4
     */
    private function updateHostByUri(): void
    {
        $host = $this->uri->getHost();
        if ($host === '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        if (isset($this->headerNames['host'])) {
            $header = $this->headerNames['host'];
        } else {
            $header = 'Host';
            // save name
            $this->headerNames['host'] = 'Host';
        }

        // Ensure Host is the first header.
        $this->headers = [$header => [$host]] + $this->headers;
    }

    /**
     * @param mixed $method
     */
    private function assertMethod($method): void
    {
        if (!is_string($method) || $method === '') {
            throw new InvalidArgumentException('Method must be a non-empty string.');
        }
    }

    /**
     * Is GET method
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Is POST method
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Is PATCH method
     *
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->method === 'PATCH';
    }

    /**
     * Is PUT method
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    /**
     * Is DELETE method
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }

    /**
     * Get client supported languages from header
     * eg: `Accept-Language:zh-CN, zh;q=0.8, en;q=0.5`
     *
     * @return array [['zh-CN', 1], ['zh', 0.8]]
     */
    public function getAcceptLanguages(): array
    {
        $ls = [];

        if ($value = $this->getHeaderLine('Accept-Language')) {
            $value = str_replace(' ', '', $value);

            if (strpos($value, ',')) {
                $nodes = explode(',', $value);
            } else {
                $nodes = [$value];
            }

            foreach ($nodes as $node) {
                if (strpos($node, ';')) {
                    $info    = explode(';', $node);
                    $info[1] = (float)substr($info[1], 2);
                } else {
                    $info = [$node, 1.0];
                }

                $ls[] = $info;
            }
        }

        return $ls;
    }

    /**
     * get client supported languages from header
     * eg: `Accept-Encoding:gzip, deflate, sdch, br`
     *
     * @return array
     */
    public function getAcceptEncodes(): array
    {
        $ens = [];

        if ($value = $this->getHeaderLine('Accept-Encoding')) {
            if (strpos($value, ';')) {
                [$value,] = explode(';', $value, 2);
            }

            $value = str_replace(' ', '', $value);
            $ens   = explode(',', $value);
        }

        return $ens;
    }
}