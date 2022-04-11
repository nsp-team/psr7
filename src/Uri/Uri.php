<?php

namespace NspTeam\Psr7\Uri;

use NspTeam\Psr7\Concern\UriTrait;
use Psr\Http\Message\UriInterface;
/**
 * URI 数据对象。
 *
 * 此接口按照 RFC 3986 来构建 HTTP URI，提供了一些通用的操作，你可以自由的对此接口
 * 进行扩展。你可以使用此 URI 接口来做 HTTP 相关的操作，也可以使用此接口做任何 URI
 * 相关的操作。
 *
 * 此接口的实例化对象被视为无法修改的，所有能修改状态的方法，都 **必须** 有一套机制，在内部保
 * 持好原有的内容，然后把修改状态后的，已新的实例返回。
 *
 * 通常，HOST 信息也将出现在请求消息中。对于服务器端的请求，通常可以在服务器参数中发现此信息。
 *
 * @see [URI 通用标准规范](http://tools.ietf.org/html/rfc3986)
 * @see [PSR-7 HTTP消息接口规范中文说明](https://learnku.com/docs/psr/psr-7-http-message/1616#417c7c)
 */
class Uri implements UriInterface
{
    /**
     * Absolute http and https URIs require a host per RFC 7230 Section 2.7
     * but in generic URIs the host can be empty. So for http(s) URIs
     * we apply this default host when no host is given yet to form a
     * valid URI.
     */
    public const HTTP_DEFAULT_HOST = 'localhost';

    /**
     * Valid schemes
     */
    private const VALID_SCHEME = [
        ''      => 1,
        'https' => 1,
        'http'  => 1,
        'ws'    => 1,
        'wss'   => 1,
    ];

    private const DEFAULT_PORTS = [
        'http'   => 80,
        'https'  => 443,
        'ftp'    => 21,
        'gopher' => 70,
        'nntp'   => 119,
        'news'   => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap'   => 143,
        'pop'    => 110,
        'ldap'   => 389,
    ];

    /**
     * Default uri params
     */
    private const DEFAULT_PARAMS = [
        'scheme'   => 'http',
        'host'     => 'localhost', // 80
        'user'     => '',
        'pass'     => '',
        'path'     => '/',
        'query'    => '',
        'fragment' => '',
    ];

    /**
     * @var string
     */
    private static $charUnreserved = 'a-zA-Z0-9_\-\.~';
    /**
     * @var string
     */
    private static $charSubDelims = '!\$&\'\(\)\*\+,;=';
    /**
     * @var array
     */
    private static $replaceQuery = ['=' => '%3D', '&' => '%26'];

    /**
     * Uri scheme
     *
     * @var string
     */
    private $scheme = '';

    /**
     * user info.
     *
     * @var string
     */
    private $userInfo = '';

    /**
     * string Uri host.
     *
     * @var string
     */
    private $host = '';

    /**
     * Uri port.
     *
     * @var int|null
     */
    private $port;

    /**
     * Uri path.
     *
     * @var string
     */
    private $path = '';

    /**
     * Uri query string.
     *
     * @var string
     */
    private $query = '';

    /**
     * Uri fragment.
     *
     * @var string
     */
    private $fragment = '';

    /**
     * Storage some params for after use.
     * @var array
     * [
     *  host  => '', // it's from headers
     *  https => '',
     *  path  => '',
     *  query => '',
     *  http_host => '',
     *  server_name => '',
     *  server_addr => '',
     *  server_port => '',
     * ]
     */
    private $params = array();

    use UriTrait;

    /**
     * @param string $uri URI to parse
     */
    public function __construct(string $uri = '', array $params = [])
    {
        // Save some params
        $this->params = $params;

        // weak type check to also accept null until we can add scalar type hints
        if ($uri !== '') {
            // If params is empty, padding defaults data
            if (!$params) {
                $this->params = self::DEFAULT_PARAMS;
            }

            $parts = parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException("Unable to parse URI: $uri");
            }
            $this->applyParts($parts);
        }
    }

    /**
     * 从 URI 中取出 scheme.
     * 如果不存在 Scheme，此方法 **必须** 返回空字符串.
     * 根据 RFC 3986 规范 3.1 章节，返回的数据 **必须** 是小写字母.
     * 最后部分的「:」字串不属于 Scheme，**不得** 作为返回数据的一部分
     * @inheritDoc
     */
    public function getScheme(): string
    {
        // Init on first get
        if (!$this->scheme) {
            $this->scheme = 'http';
        }

        return $this->scheme;
    }

    /**
     * 返回 URI 认证信息。
     * 如果没有 URI 认证信息的话，**必须** 返回一个空字符串。
     * URI 的认证信息语法是：
     *  <pre>
     * [user-info@]host[:port]
     * </pre>
     *
     * 如果端口部分没有设置，或者端口不是标准端口，**不应该** 包含在返回值内。
     * @inheritDoc
     */
    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        // 如果端口部分没有设置，或者端口不是标准端口，**不应该** 包含在返回值内
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * 从 URI 中获取用户信息。
     * 如果不存在用户信息，此方法 **必须** 返回一个空字符串。
     * 格式："username[:password]"
     * @inheritDoc
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * 从 URI 中获取 HOST 信息。
     * 如果 URI 中没有此值，**必须** 返回空字符串。
     * 根据 RFC 3986 规范 3.2.2 章节，返回的数据 **必须** 是小写字母。
     * @inheritDoc
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 从 URI 中获取端口信息。
     * 如果端口信息是与当前 Scheme 的标准端口不匹配的话，就使用整数值的格式返回，如果是一样的话，**应该** 返回 `null` 值。
     * 如果不存在端口和 Scheme 信息，**必须** 返回 `null` 值。
     *
     * 如果不存在端口数据，但是存在 Scheme 的话，**可能** 返回 Scheme 对应的标准端口，但是 **应该** 返回 `null`。
     * @inheritDoc
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * 从 URI 中获取路径信息。
     * 路径可以是空的，或者是绝对的（以斜线「/」开头），或者相对路径（不以斜线开头）。
     * 实现 **必须** 支持所有三种语法。
     *
     * 根据 RFC 7230 第 2.7.3 节，通常空路径「」和绝对路径「/」被认为是相同的。
     * 但是这个方法 **不得** 自动进行这种规范化，因为在具有修剪的基本路径的上下文中，
     * 例如前端控制器中，这种差异将变得显著。用户的任务就是可以将「」和「/」都处理好。
     *
     * 返回的值 **必须** 是百分号编码，但 **不得** 对任何字符进行双重编码。
     * 要确定要编码的字符，请参阅 RFC 3986 第 2 节和第 3.3 节。
     *
     * 例如，如果值包含斜线（「/」）而不是路径段之间的分隔符，则该值必须以编码形式（例如「%2F」）传递给实例。
     * @inheritDoc
     */
    public function getPath(): string
    {
        // Init on get
        if ('' === $this->path) {
            // $this->path = $this->params['path'];
            $this->path = $this->filterPath($this->params['path']);
        }
        return $this->path;
    }

    /**
     * 获取 URI 中的查询字符串。
     * 如果不存在查询字符串，则此方法必须返回空字符串。
     * 前导的「?」字符不是查询字符串的一部分，**不得** 添加在返回值中。
     *
     * 返回的值 **必须** 是百分号编码，但 **不得** 对任何字符进行双重编码。
     * 要确定要编码的字符，请参阅 RFC 3986 第 2 节和第 3.4 节。
     *
     * 例如，如果查询字符串的键值对中的值包含不做为值之间分隔符的（「&」），则该值必须以编码形式传递（例如「%26」）到实例。
     * @inheritDoc
     */
    public function getQuery(): string
    {
        // Init on get
        if ('' === $this->query) {
            // $this->query = $this->params['query'];
            $this->query = $this->filterQueryAndFragment($this->params['query']);
        }
        return $this->query;
    }

    /**
     * 获取 URI 中的片段（Fragment）信息。
     *
     * 如果没有片段信息，此方法 **必须** 返回空字符串。
     * 前导的「#」字符不是片段的一部分，**不得** 添加在返回值中。
     *
     * 返回的值 **必须** 是百分号编码，但 **不得** 对任何字符进行双重编码。
     * 要确定要编码的字符，请参阅 RFC 3986 第 2 节和第 3.5 节。
     * @inheritDoc
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * 返回具有指定 Scheme 的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含指定 Scheme 的实例。
     *
     * 实现 **必须** 支持大小写不敏感的「http」和「https」的 Scheme，需要的时候 **可能** 支持其他的 Scheme
     *
     * 返回具有指定 Scheme 的新实例。
     * @inheritDoc
     */
    public function withScheme($scheme)
    {
        // 入参$scheme 不区分大小写，程序进行统一转换
        $scheme = $this->filterScheme($scheme);
        if ($this->scheme === $scheme) {
            return $this;
        }

        $clone = clone $this;

        $clone->scheme = $scheme;
        $clone->removeDefaultPort();
        $clone->validateState();

        return $clone;
    }

    /**
     * @inheritDoc
     * @param string $user The user name to use for authority.
     * @param null|string $password The password associated with $user.
     */
    public function withUserInfo($user, $password = null)
    {
        $info = $user;
        if ($password !== '' && !is_null($password)) {
            $info .= ':' . $password;
        }

        if ($this->userInfo === $info) {
            return $this;
        }

        $clone = clone $this;

        $clone->userInfo = $info;
        $clone->validateState();
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withHost($host)
    {
        $host = $this->filterHost($host);
        if ($this->host === $host) {
            return $this;
        }

        $clone = clone $this;

        $clone->host = $host;
        $clone->validateState();
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withPort($port)
    {
        $port = $this->filterPort($port);
        if ($this->port === $port) {
            return $this;
        }
        $clone = clone $this;

        $clone->port = $port;
        $clone->removeDefaultPort();
        $clone->validateState();
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withPath($path)
    {
        $path = $this->filterPath($path);
        if ($this->path === $path) {
            return $this;
        }

        $clone = clone $this;

        $clone->path = $path;
        $clone->validateState();
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withQuery($query)
    {
        $query = $this->filterQueryAndFragment($query);
        if ($this->query === $query) {
            return $this;
        }
        $clone = clone $this;

        $clone->query = $query;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withFragment($fragment)
    {
        $fragment = $this->filterQueryAndFragment($fragment);
        if ($this->fragment === $fragment) {
            return $this;
        }

        $clone = clone $this;

        $clone->fragment = $fragment;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return self::composeComponents(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    /**
     * Composes a URI reference string from its various components.
     *
     * Usually this method does not need to be called manually but instead is used indirectly via
     * `Psr\Http\Message\UriInterface::__toString`.
     *
     * PSR-7 UriInterface treats an empty component the same as a missing component as
     * getQuery(), getFragment() etc. always return a string. This explains the slight
     * difference to RFC 3986 Section 5.3.
     *
     * Another adjustment is that the authority separator is added even when the authority is missing/empty
     * for the "file" scheme. This is because PHP stream functions like `file_get_contents` only work with
     * `file:///myfile` but not with `file:/myfile` although they are equivalent according to RFC 3986. But
     * `file:///` is the more common syntax for the file scheme anyway (Chrome for example redirects to
     * that format).
     *
     * @param string $scheme
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param string $fragment
     *
     * @return string
     *
     * @link https://tools.ietf.org/html/rfc3986#section-5.3
     */
    public static function composeComponents(string $scheme, string $authority, string $path, string $query, string $fragment): string
    {
        $uri = '';

        // weak type checks to also accept null until we can add scalar type hints
        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }
        if ($authority !== '' || $scheme === 'file') {
            $uri .= '//' . $authority;
        }

        $uri .= $path;

        if ($query !== '') {
            $uri .= '?' . $query;
        }
        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Creates a URI from a hash of `parse_url` components.
     *
     * @link http://php.net/manual/en/function.parse-url.php
     */
    public static function fromParts(array $parts): UriInterface
    {
        $uri = new self();
        $uri->applyParts($parts);
        $uri->validateState();

        return $uri;
    }
}