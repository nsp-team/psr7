<?php

namespace NspTeamTest\Psr7\Unit;

use NspTeam\Psr7\Request\PsrRequest;
use NspTeam\Psr7\Uri\Uri;
use Psr\Http\Message\StreamInterface;

class PsrRequestTest extends \PHPUnit\Framework\TestCase
{
    public function testHeaders(): void
    {
        $r = new PsrRequest('GET','http://www.foo.com/');
        var_dump((string)$r->getBody());
        $r = $r->withHeaders([
            'accept-language' => 'zh-CN, zh;q=0.8, en;q=0.5',
        ]);

        $this->assertSame('zh-CN, zh;q=0.8, en;q=0.5', $r->getHeaderLine('Accept-Language'));
        $this->assertSame('zh-CN, zh;q=0.8, en;q=0.5', $r->getHeaderLine('accept-language'));

        // GetAcceptLanguages
        $ls = $r->getAcceptLanguages();
        $this->assertNotEmpty($ls);
        $this->assertSame(['zh-CN', 1.0], $ls[0]);
        $this->assertSame(['zh', 0.8], $ls[1]);
        $this->assertSame(['en', 0.5], $ls[2]);
    }

    public function testRequestUriMayBeString(): void
    {
        $r = new PsrRequest('GET', '/');
        self::assertSame('/', (string) $r->getUri());
    }

    public function testRequestUriMayBeUri(): void
    {
        $uri = new Uri('/');
        $r = new PsrRequest('GET', $uri);
        self::assertSame($uri, $r->getUri());
    }

    public function testValidateRequestUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PsrRequest('GET', '///');
    }

    public function testCanConstructWithBody(): void
    {
        $r = new PsrRequest('GET', '/', [], 'baz');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('baz', (string) $r->getBody());
    }

    public function testNullBody(): void
    {
        $r = new PsrRequest('GET', '/', [], null);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function testSameInstanceWhenSameUri(): void
    {
        $r1 = new PsrRequest('GET', 'http://foo.com');
        $r2 = $r1->withUri($r1->getUri());
        self::assertSame($r1, $r2);
    }

    public function testWithRequestTarget(): void
    {
        $r1 = new PsrRequest('GET', '/');
        $r2 = $r1->withRequestTarget('*');
        self::assertSame('*', $r2->getRequestTarget());
        self::assertSame('/', $r1->getRequestTarget());
    }

    public function testHostIsAddedFirst(): void
    {
        $r = new PsrRequest('GET', 'http://foo.com/baz?bar=bam', ['Foo' => 'Bar']);
        self::assertSame([
            'Host' => ['foo.com'],
            'Foo'  => ['Bar']
        ], $r->getHeaders());
    }

    public function testCanGetHeaderAsCsv(): void
    {
        $r = new PsrRequest('GET', 'http://foo.com/baz?bar=bam', [
            'Foo' => ['a', 'b', 'c']
        ]);
        self::assertSame('a, b, c', $r->getHeaderLine('Foo'));
        self::assertSame('', $r->getHeaderLine('Bar'));
    }
}