<?php

namespace NspTeamTest\Psr7\Unit;

use NspTeam\Psr7\Uri\Uri;

class UriTest extends \PHPUnit\Framework\TestCase
{
    public function testBaseUri():void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path/123?q=abc#test');
        self::assertNotEmpty($uri);

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path/123', $uri->getPath());
        self::assertSame('q=abc', $uri->getQuery());
        self::assertSame('test', $uri->getFragment());
        self::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);

        $uri = new Uri('', [
            'path' => '/home/index'
        ]);

        $this->assertNotEmpty($uri);
    }

    /**
     */
    public function testIssue792(): void
    {
        $uri = new Uri('http://ic.clive.domain.com/db');
        $this->assertNotEmpty($uri);

        $this->assertSame('ic.clive.domain.com', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('/db', $uri->getPath());
        $this->assertSame('ic.clive.domain.com', $uri->getAuthority());
    }

    public function testCanTransformAndRetrievePartsIndividually(): void
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withUserInfo('user', 'pass')
            ->withHost('example.com')
            ->withPort(8080)
            ->withPath('/path/123')
            ->withQuery('q=abc')
            ->withFragment('test');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path/123', $uri->getPath());
        self::assertSame('q=abc', $uri->getQuery());
        self::assertSame('test', $uri->getFragment());
        self::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    /**
     * @dataProvider getValidUris
     */
    public function testValidUrisStayValid(string $input): void
    {
        $uri = new Uri($input);

        self::assertSame($input, (string) $uri);
    }

    /**
     * @dataProvider getValidUris
     */
    public function testFromParts(string $input): void
    {
        $uri = Uri::fromParts(parse_url($input));

        self::assertSame($input, (string) $uri);
    }


    public function getValidUris(): iterable
    {
        return [
            ['urn:path-rootless'],
            ['urn:path:with:colon'],
            ['urn:/path-absolute'],
            ['urn:/'],
            // only scheme with empty path
            ['urn:'],
            // only path
            ['/'],
            ['relative/'],
            ['0'],
            // same document reference
            [''],
            // network path without scheme
            ['//example.org'],
            ['//example.org/'],
            ['//example.org?q#h'],
            // only query
            ['?q'],
            ['?q=abc&foo=bar'],
            // only fragment
            ['#fragment'],
            // dot segments are not removed automatically
            ['./foo/../bar'],
        ];
    }

    public function getInvalidUris(): iterable
    {
        return [
            // parse_url() requires the host component which makes sense for http(s)
            // but not when the scheme is not known or different. So '//' or '///' is
            // currently invalid as well but should not according to RFC 3986.
            ['http://'],
            ['urn://host:with:colon'], // host cannot contain ":"
        ];
    }

    /**
     * @dataProvider getInvalidUris
     */
    public function testInvalidUrisThrowException(string $invalidUri): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Uri($invalidUri);
    }

    public function testWithPortCannotBeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: -1. Must be between 0 and 65535');
        (new Uri())->withPort(-1);
    }
}