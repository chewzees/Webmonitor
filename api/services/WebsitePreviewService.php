<?php
declare(strict_types=1);

/**
 * Smarter URL analyzer for website form autofill.
 * Uses HTML meta, Open Graph, Twitter cards, JSON-LD, headings, content signals,
 * TLD/path heuristics, and weighted category scoring.
 */
final class WebsitePreviewService
{
    private const MAX_BYTES = 750_000;
    private const TIMEOUT_SEC = 10;

    /** @return array<string, mixed> */
    public static function analyze(string $url): array
    {
        $url = self::normalizeUrl($url);
        self::assertSafeUrl($url);

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '/');
        $query = (string) ($parts['query'] ?? '');
        $domainName = self::domainLabel($host);
        $registrable = self::registrableDomain($host);

        $fetched = self::fetchPage($url);
        $html = $fetched['body'] ?? '';
        $contentType = $fetched['contentType'] ?? '';
        $finalUrl = $fetched['finalUrl'] ?? $url;
        $statusCode = $fetched['statusCode'];

        $meta = self::extractMeta($html);
        $jsonLd = self::extractJsonLd($html);
        $signals = self::collectSignals($host, $path, $query, $meta, $jsonLd, $html, $contentType, $finalUrl);
        $purpose = self::scorePurpose($signals, $meta, $jsonLd, $host, $path);
        $tech = self::detectTech($html, $meta, $signals);

        $name = self::suggestName($meta, $jsonLd, $domainName, $host, $path, $purpose);
        $slug = self::slugify(self::preferSlugSource($name, $domainName, $path));
        $description = self::suggestDescription($purpose, $meta, $jsonLd, $name, $host, $tech, $statusCode);
        $method = self::suggestMethod($path, $contentType, $purpose['category']);
        $expected = self::suggestExpectedStatus($statusCode, $contentType);

        $confidence = (int) round($purpose['confidence'] * 100);

        return [
            'url' => $url,
            'finalUrl' => $finalUrl,
            'reachable' => $fetched['ok'],
            'statusCode' => $statusCode,
            'suggested' => [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'expectedStatus' => $expected,
                'method' => $method,
            ],
            'analysis' => [
                'purpose' => $purpose['label'],
                'category' => $purpose['category'],
                'summary' => $purpose['summary'],
                'confidence' => $confidence,
                'signals' => array_slice($purpose['matchedSignals'], 0, 8),
                'tech' => $tech,
                'title' => $meta['title'],
                'siteName' => $meta['siteName'],
                'metaDescription' => $meta['description'],
                'headline' => $meta['h1'],
                'host' => $host,
                'domain' => $registrable,
                'contentType' => $contentType ?: null,
            ],
            'message' => $fetched['ok']
                ? sprintf('Analyzed with %d%% confidence — %s.', $confidence, strtolower($purpose['label']))
                : ($fetched['error'] ?? 'Could not fully fetch the page; suggestions use URL heuristics.'),
        ];
    }

    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new AppException('URL is required', 400, 'VALIDATION_ERROR');
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new AppException('Enter a valid http(s) URL', 400, 'VALIDATION_ERROR');
        }
        return $url;
    }

    private static function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new AppException('Only http and https URLs are allowed', 400, 'VALIDATION_ERROR');
        }
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || $host === '0.0.0.0') {
            throw new AppException('That host cannot be analyzed', 400, 'VALIDATION_ERROR');
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            }
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                throw new AppException('Private or local network addresses cannot be analyzed', 400, 'VALIDATION_ERROR');
            }
        }
    }

    private static function isPrivateIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /** @return array{ok:bool,statusCode:int|null,body:string,error:?string,contentType:?string,finalUrl:?string} */
    private static function fetchPage(string $url): array
    {
        if (!function_exists('curl_init')) {
            return self::fetchPageStream($url);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SEC,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEC,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WebMonitor/1.1; +smart-autofill)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.8',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'statusCode' => null,
                'body' => '',
                'error' => $err ?: 'Fetch failed',
                'contentType' => null,
                'finalUrl' => null,
            ];
        }
        if (strlen($body) > self::MAX_BYTES) {
            $body = substr($body, 0, self::MAX_BYTES);
        }

        return [
            'ok' => $status > 0 && $status < 500,
            'statusCode' => $status > 0 ? $status : null,
            'body' => (string) $body,
            'error' => $status >= 500 ? 'Remote server error' : null,
            'contentType' => $contentType !== '' ? strtolower(explode(';', $contentType)[0]) : null,
            'finalUrl' => $finalUrl !== '' ? $finalUrl : $url,
        ];
    }

    /** @return array{ok:bool,statusCode:int|null,body:string,error:?string,contentType:?string,finalUrl:?string} */
    private static function fetchPageStream(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SEC,
                'header' => "User-Agent: Mozilla/5.0 (compatible; WebMonitor/1.1; +smart-autofill)\r\nAccept: text/html,application/json;q=0.9,*/*;q=0.8\r\n",
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx, 0, self::MAX_BYTES);
        if ($body === false) {
            return [
                'ok' => false,
                'statusCode' => null,
                'body' => '',
                'error' => 'Fetch failed',
                'contentType' => null,
                'finalUrl' => null,
            ];
        }
        $status = null;
        $contentType = null;
        if (!empty($http_response_header) && is_array($http_response_header)) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
            foreach ($http_response_header as $headerLine) {
                if (stripos($headerLine, 'Content-Type:') === 0) {
                    $contentType = strtolower(trim(explode(';', substr($headerLine, 13))[0]));
                }
            }
        }
        return [
            'ok' => true,
            'statusCode' => $status,
            'body' => $body,
            'error' => null,
            'contentType' => $contentType,
            'finalUrl' => $url,
        ];
    }

    /**
     * @return array{
     *   title:?string,description:?string,siteName:?string,keywords:?string,
     *   ogType:?string,twitterTitle:?string,canonical:?string,h1:?string,
     *   applicationName:?string,generator:?string,robots:?string
     * }
     */
    private static function extractMeta(string $html): array
    {
        $empty = [
            'title' => null,
            'description' => null,
            'siteName' => null,
            'keywords' => null,
            'ogType' => null,
            'twitterTitle' => null,
            'canonical' => null,
            'h1' => null,
            'applicationName' => null,
            'generator' => null,
            'robots' => null,
        ];
        if ($html === '') {
            return $empty;
        }

        $getMeta = static function (string $html, array $names): ?string {
            foreach ($names as $name) {
                $q = preg_quote($name, '/');
                if (preg_match('/<meta[^>]+(?:name|property)=["\']' . $q . '["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
                    || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:name|property)=["\']' . $q . '["\']/i', $html, $m)) {
                    $val = self::cleanText(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
            return null;
        };

        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = self::cleanText(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $ogTitle = $getMeta($html, ['og:title']);
        if ($ogTitle) {
            $title = $ogTitle;
        }

        $h1 = null;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $h1 = self::cleanText(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $canonical = null;
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']/i', $html, $m)) {
            $canonical = trim($m[1]);
        }

        return [
            'title' => $title,
            'description' => $getMeta($html, ['description', 'og:description', 'twitter:description']),
            'siteName' => $getMeta($html, ['og:site_name', 'application-name']),
            'keywords' => $getMeta($html, ['keywords']),
            'ogType' => $getMeta($html, ['og:type']),
            'twitterTitle' => $getMeta($html, ['twitter:title']),
            'canonical' => $canonical,
            'h1' => $h1,
            'applicationName' => $getMeta($html, ['application-name']),
            'generator' => $getMeta($html, ['generator']),
            'robots' => $getMeta($html, ['robots']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function extractJsonLd(string $html): array
    {
        if ($html === '' || !preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        )) {
            return [];
        }

        $items = [];
        foreach ($matches[1] as $raw) {
            $raw = html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                foreach ($decoded['@graph'] as $node) {
                    if (is_array($node)) {
                        $items[] = $node;
                    }
                }
            } else {
                $items[] = $decoded;
            }
        }
        return $items;
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $jsonLd
     * @return list<string>
     */
    private static function collectSignals(
        string $host,
        string $path,
        string $query,
        array $meta,
        array $jsonLd,
        string $html,
        string $contentType,
        string $finalUrl,
    ): array {
        $signals = [];
        $blob = strtolower(implode("\n", array_filter([
            $host,
            $path,
            $query,
            $finalUrl,
            $contentType,
            $meta['title'] ?? '',
            $meta['description'] ?? '',
            $meta['keywords'] ?? '',
            $meta['siteName'] ?? '',
            $meta['ogType'] ?? '',
            $meta['h1'] ?? '',
            $meta['generator'] ?? '',
            $meta['applicationName'] ?? '',
        ])));

        foreach ($jsonLd as $node) {
            $type = $node['@type'] ?? null;
            if (is_array($type)) {
                $type = implode(' ', $type);
            }
            if (is_string($type) && $type !== '') {
                $signals[] = 'jsonld:' . strtolower($type);
                $blob .= "\n" . strtolower($type);
            }
            foreach (['name', 'headline', 'description', 'alternateName'] as $field) {
                if (!empty($node[$field]) && is_string($node[$field])) {
                    $blob .= "\n" . strtolower($node[$field]);
                }
            }
        }

        if ($contentType !== '') {
            $signals[] = 'ctype:' . $contentType;
        }
        if (str_contains($contentType, 'json') || preg_match('#\.(json)(\?|$)#i', $path)) {
            $signals[] = 'format:json';
        }
        if (str_contains($contentType, 'xml') || preg_match('#\.(xml|rss|atom)(\?|$)#i', $path)) {
            $signals[] = 'format:xml';
        }
        if (preg_match('#^/(api|v\d+|graphql|rest)(/|$)#i', $path) || str_starts_with($host, 'api.')) {
            $signals[] = 'path:api';
        }
        if (preg_match('#/(login|signin|sign-in|auth|oauth|sso)(/|$)#i', $path . ' ' . $blob)) {
            $signals[] = 'auth:login';
        }
        if (preg_match('#/(docs|documentation|reference|developers?)(/|$)#i', $path) || str_starts_with($host, 'docs.')) {
            $signals[] = 'path:docs';
        }
        if (preg_match('#/(blog|news|articles?|posts?)(/|$)#i', $path)) {
            $signals[] = 'path:content';
        }
        if (preg_match('#/(cart|checkout|shop|store|product)(/|$)#i', $path . ' ' . $blob)) {
            $signals[] = 'path:commerce';
        }
        if (preg_match('#/(status|health|uptime)(/|$)#i', $path) || str_starts_with($host, 'status.')) {
            $signals[] = 'path:status';
        }
        if (str_ends_with($host, '.edu') || str_contains($blob, 'university') || str_contains($blob, 'course')) {
            $signals[] = 'sector:edu';
        }
        if (str_ends_with($host, '.gov') || str_contains($blob, 'government')) {
            $signals[] = 'sector:gov';
        }
        if (preg_match('/\b(wordpress|wp-content|shopify|woocommerce|squarespace|wix|webflow|drupal|joomla|ghost)\b/i', $html . ' ' . ($meta['generator'] ?? ''))) {
            $signals[] = 'cms:detected';
        }
        if (preg_match('/\b(react|next\.js|vue|nuxt|angular|svelte|laravel|django|rails|express)\b/i', $html)) {
            $signals[] = 'stack:frontend-or-framework';
        }
        if (preg_match('/\b(add to cart|buy now|free shipping|sku|price)\b/i', $html)) {
            $signals[] = 'copy:commerce';
        }
        if (preg_match('/\b(sign in|log in|create account|dashboard|workspace)\b/i', $html)) {
            $signals[] = 'copy:saas';
        }
        if (preg_match('/\b(openapi|swagger|graphql|endpoint|bearer token|api key)\b/i', $html . ' ' . $blob)) {
            $signals[] = 'copy:api';
        }
        if (preg_match('/\b(all systems operational|incident|uptime|degraded performance)\b/i', $html)) {
            $signals[] = 'copy:status';
        }
        if (preg_match('/\b(privacy policy|terms of service|contact us|about us)\b/i', $html)) {
            $signals[] = 'copy:corporate';
        }

        // Keep unique ordered signals + pass blob via special key handled by caller through return of both?
        // We'll attach blob as last pseudo via returning associative in scorePurpose by rebuilding.
        return array_values(array_unique($signals));
    }

    /**
     * @param list<string> $signals
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $jsonLd
     * @return array{category:string,label:string,summary:string,confidence:float,matchedSignals:list<string>}
     */
    private static function scorePurpose(
        array $signals,
        array $meta,
        array $jsonLd,
        string $host,
        string $path,
    ): array {
        $blob = strtolower(implode(' ', array_filter([
            $host,
            $path,
            $meta['title'] ?? '',
            $meta['description'] ?? '',
            $meta['keywords'] ?? '',
            $meta['siteName'] ?? '',
            $meta['ogType'] ?? '',
            $meta['h1'] ?? '',
            $meta['generator'] ?? '',
            implode(' ', $signals),
        ])));

        foreach ($jsonLd as $node) {
            $type = $node['@type'] ?? '';
            if (is_array($type)) {
                $type = implode(' ', $type);
            }
            if (is_string($type)) {
                $blob .= ' ' . strtolower($type);
            }
        }

        /** @var list<array{0:string,1:string,2:list<array{0:string,1:float}>,3?:float}> $catalog */
        $catalog = [
            ['api', 'API / backend service', [
                ['path:api', 0.45], ['format:json', 0.4], ['copy:api', 0.35], ['ctype:application/json', 0.5],
                ['graphql', 0.4], ['swagger', 0.35], ['openapi', 0.35], ['jsonld:apireference', 0.3],
                ['api.', 0.35], ['/v1', 0.2], ['/v2', 0.2],
            ]],
            ['docs', 'Documentation site', [
                ['path:docs', 0.55], ['documentation', 0.35], ['developer', 0.2], ['readme', 0.25],
                ['jsonld:techarticle', 0.3], ['api docs', 0.35], ['reference', 0.15], ['docs.', 0.45],
            ]],
            ['shop', 'E-commerce / online store', [
                ['path:commerce', 0.45], ['copy:commerce', 0.4], ['shopify', 0.45], ['woocommerce', 0.4],
                ['jsonld:product', 0.5], ['jsonld:store', 0.45], ['jsonld:offer', 0.35],
                ['cart', 0.25], ['checkout', 0.3], ['buy now', 0.25],
            ]],
            ['saas', 'SaaS / product application', [
                ['copy:saas', 0.4], ['auth:login', 0.3], ['dashboard', 0.25], ['workspace', 0.3],
                ['jsonld:softwareapplication', 0.5], ['app.', 0.25], ['platform', 0.15],
            ]],
            ['blog', 'Blog / content publication', [
                ['path:content', 0.4], ['jsonld:blog', 0.5], ['jsonld:blogposting', 0.5], ['jsonld:article', 0.35],
                ['wordpress', 0.3], ['ghost', 0.3], ['medium.com', 0.4], ['newsletter', 0.25],
            ]],
            ['news', 'News / media outlet', [
                ['jsonld:newsarticle', 0.55], ['news', 0.25], ['press', 0.2], ['magazine', 0.25],
            ]],
            ['status', 'Status / uptime page', [
                ['path:status', 0.55], ['copy:status', 0.5], ['status.', 0.45], ['uptime', 0.3], ['incident', 0.25],
            ]],
            ['repo', 'Code repository / developer platform', [
                ['github.com', 0.6], ['gitlab', 0.5], ['bitbucket', 0.5], ['repository', 0.3], ['jsonld:softwaresourcecode', 0.4],
            ]],
            ['social', 'Social / community platform', [
                ['facebook', 0.4], ['instagram', 0.4], ['linkedin', 0.35], ['discord', 0.35], ['reddit', 0.35],
                ['twitter', 0.35], ['x.com', 0.3], ['community', 0.15],
            ]],
            ['edu', 'Education / learning platform', [
                ['sector:edu', 0.5], ['.edu', 0.45], ['course', 0.3], ['university', 0.35], ['academy', 0.3], ['learn', 0.15],
            ]],
            ['finance', 'Finance / payments', [
                ['stripe', 0.4], ['bank', 0.3], ['billing', 0.25], ['payment', 0.3], ['wallet', 0.25], ['fintech', 0.3],
            ]],
            ['health', 'Health / medical', [
                ['clinic', 0.35], ['hospital', 0.4], ['medical', 0.3], ['healthcare', 0.35], ['patient', 0.2],
            ]],
            ['gov', 'Government / public sector', [
                ['sector:gov', 0.6], ['.gov', 0.55], ['government', 0.35],
            ]],
            ['cdn', 'CDN / static asset host', [
                ['cdn.', 0.5], ['cloudfront', 0.45], ['static.', 0.35], ['assets.', 0.25],
            ]],
            ['portfolio', 'Portfolio / personal site', [
                ['portfolio', 0.4], ['resume', 0.35], ['cv', 0.2], ['personal', 0.15], ['jsonld:person', 0.45],
            ]],
            ['corporate', 'Company / marketing website', [
                ['copy:corporate', 0.25], ['jsonld:organization', 0.4], ['about us', 0.2], ['cms:detected', 0.15],
                ['og:type:website', 0.1],
            ]],
        ];

        $scores = [];
        $matched = [];
        foreach ($catalog as $entry) {
            [$category, $label, $rules] = $entry;
            $score = 0.0;
            $hits = [];
            foreach ($rules as [$needle, $weight]) {
                $found = in_array($needle, $signals, true) || str_contains($blob, $needle);
                // also allow signal keys like jsonld:product via str_contains on signals joined
                if (!$found) {
                    foreach ($signals as $sig) {
                        if ($sig === $needle || str_ends_with($sig, ':' . $needle) || str_contains($sig, $needle)) {
                            $found = true;
                            break;
                        }
                    }
                }
                if ($found) {
                    $score += $weight;
                    $hits[] = $needle;
                }
            }
            if (($meta['ogType'] ?? '') !== '' && $category === 'corporate' && str_contains(strtolower((string) $meta['ogType']), 'website')) {
                $score += 0.1;
                $hits[] = 'og:type:website';
            }
            $scores[$category] = [
                'score' => $score,
                'label' => $label,
                'hits' => $hits,
            ];
        }

        uasort($scores, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $topCategory = array_key_first($scores) ?: 'website';
        $top = $scores[$topCategory];
        $second = 0.0;
        $i = 0;
        foreach ($scores as $row) {
            if ($i === 1) {
                $second = $row['score'];
                break;
            }
            $i++;
        }

        if ($top['score'] < 0.25) {
            $topCategory = 'website';
            $top = [
                'score' => 0.2,
                'label' => 'General website',
                'hits' => ['fallback:general'],
            ];
        }

        $confidence = min(0.97, max(0.2, $top['score'] / 1.35));
        if ($top['score'] - $second < 0.15) {
            $confidence = max(0.2, $confidence - 0.1);
        }
        if (($meta['description'] ?? '') !== '' || ($meta['title'] ?? '') !== '') {
            $confidence = min(0.98, $confidence + 0.08);
        }
        if ($jsonLd !== []) {
            $confidence = min(0.99, $confidence + 0.06);
        }
        // Strong boost for unambiguous host matches (unless docs subdomain won)
        if (
            $topCategory !== 'docs'
            && preg_match('/\b(github\.com|gitlab\.com|bitbucket\.org|stripe\.com|shopify\.com)\b/i', $host)
            && !str_starts_with($host, 'docs.')
        ) {
            $confidence = min(0.99, max($confidence, 0.82));
        }
        if ($topCategory === 'docs' && (str_starts_with($host, 'docs.') || str_contains($path, '/docs'))) {
            $confidence = min(0.99, max($confidence, 0.78));
        }

        $summary = self::buildSummary($top['label'], $meta, $jsonLd, $host, $top['hits']);

        return [
            'category' => $topCategory,
            'label' => $top['label'],
            'summary' => $summary,
            'confidence' => $confidence,
            'matchedSignals' => array_values(array_unique($top['hits'])),
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $jsonLd
     * @param list<string> $hits
     */
    private static function buildSummary(
        string $label,
        array $meta,
        array $jsonLd,
        string $host,
        array $hits,
    ): string {
        $about = $meta['description']
            ?: self::jsonLdFirst($jsonLd, ['description', 'headline', 'name'])
            ?: $meta['h1']
            ?: $meta['title'];

        $bits = ["Looks like a {$label}."];
        if ($about) {
            $bits[] = self::truncate($about, 150);
        } else {
            $bits[] = "Hosted at {$host} and useful to monitor for uptime and response time.";
        }
        if ($hits !== [] && !($hits === ['fallback:general'])) {
            $readable = array_slice(array_map(static function ($h) {
                $h = str_replace(['path:', 'copy:', 'jsonld:', 'format:', 'ctype:', 'sector:', 'auth:', 'cms:', 'stack:', 'fallback:'], '', $h);
                return str_replace(['-', '_'], ' ', $h);
            }, $hits), 0, 3);
            $bits[] = 'Signals: ' . implode(', ', $readable) . '.';
        } elseif ($hits === ['fallback:general']) {
            $bits[] = 'Limited metadata available on the page.';
        }
        return self::truncate(implode(' ', $bits), 320);
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $jsonLd
     */
    private static function suggestName(
        array $meta,
        array $jsonLd,
        string $domainName,
        string $host,
        string $path,
        array $purpose,
    ): string {
        $candidates = array_filter([
            $meta['siteName'] ?? null,
            $meta['applicationName'] ?? null,
            self::jsonLdFirst($jsonLd, ['name', 'alternateName', 'headline']),
            self::titleBrand($meta['title'] ?? null),
            self::titleBrand($meta['twitterTitle'] ?? null),
            $meta['h1'] ?? null,
        ], static fn ($v) => is_string($v) && trim($v) !== '');

        foreach ($candidates as $candidate) {
            $clean = self::cleanBrandName($candidate, $host);
            if ($clean !== '') {
                // Prefer shorter brand-like names over long headlines
                if (mb_strlen($clean) <= 48 || $candidate === ($meta['siteName'] ?? null)) {
                    return self::truncate($clean, 80);
                }
            }
        }

        foreach ($candidates as $candidate) {
            $clean = self::cleanBrandName($candidate, $host);
            if ($clean !== '') {
                return self::truncate($clean, 80);
            }
        }

        // Path-aware fallback for API endpoints
        if ($purpose['category'] === 'api' && preg_match('#/(v\d+|api)(/|$)#i', $path)) {
            return self::truncate(ucwords(str_replace(['-', '_'], ' ', $domainName)) . ' API', 80);
        }

        if ($domainName !== '') {
            return ucwords(str_replace(['-', '_'], ' ', $domainName));
        }
        return $host;
    }

    private static function titleBrand(?string $title): ?string
    {
        if ($title === null || trim($title) === '') {
            return null;
        }
        $parts = preg_split('/\s*[|\-–—:•]\s*/u', $title) ?: [$title];
        $first = trim((string) $parts[0]);
        return $first !== '' ? $first : null;
    }

    private static function cleanBrandName(string $name, string $host): string
    {
        $name = self::cleanText($name);
        // Drop trailing host noise
        $hostBare = preg_replace('/^www\./', '', $host) ?? $host;
        $name = preg_replace('/\s*[\|\-–—]\s*' . preg_quote($hostBare, '/') . '$/i', '', $name) ?? $name;
        $name = preg_replace('/\b(home|official site|welcome|login|sign in)\b/i', '', $name) ?? $name;
        $name = self::cleanText($name);
        if (mb_strlen($name) < 2) {
            return '';
        }
        return $name;
    }

    /**
     * @param array{category:string,label:string,summary:string,confidence:float,matchedSignals:list<string>} $purpose
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $jsonLd
     * @param list<string> $tech
     */
    private static function suggestDescription(
        array $purpose,
        array $meta,
        array $jsonLd,
        string $name,
        string $host,
        array $tech,
        ?int $statusCode,
    ): string {
        $base = $meta['description']
            ?: self::jsonLdFirst($jsonLd, ['description', 'headline'])
            ?: null;

        if ($base) {
            $desc = self::truncate($base, 220);
            $suffix = " Monitored as {$purpose['label']} ({$host}).";
            if (mb_strlen($desc) + mb_strlen($suffix) <= 300) {
                return $desc . $suffix;
            }
            return self::truncate($desc, 280);
        }

        $parts = [
            "{$name} — {$purpose['label']}.",
            "Track availability and latency for {$host}.",
        ];
        if ($tech !== []) {
            $parts[] = 'Detected: ' . implode(', ', array_slice($tech, 0, 3)) . '.';
        }
        if ($statusCode) {
            $parts[] = "Last probe HTTP {$statusCode}.";
        }
        return self::truncate(implode(' ', $parts), 280);
    }

    /** @param list<string> $signals
     *  @param array<string, mixed> $meta
     *  @return list<string>
     */
    private static function detectTech(string $html, array $meta, array $signals): array
    {
        $tech = [];
        $gen = strtolower((string) ($meta['generator'] ?? ''));
        $map = [
            'WordPress' => ['wp-content/', 'wordpress.org', 'name="generator" content="wordpress'],
            'Shopify' => ['cdn.shopify.com', 'shopify-section', 'myshopify.com'],
            'Webflow' => ['webflow.js', 'data-wf-site'],
            'Wix' => ['static.wixstatic.com', 'wix.com'],
            'Squarespace' => ['squarespace-cdn.com', 'squarespace.com'],
            'Drupal' => ['drupal.js', 'name="generator" content="drupal'],
            'Ghost' => ['ghost.org', 'content="ghost'],
            'Next.js' => ['/_next/static', '__NEXT_DATA__'],
            'Nuxt' => ['__NUXT__', '/_nuxt/'],
            'React' => ['data-reactroot', 'react-root'],
            'Vue' => ['data-v-app', '__vue_app__'],
            'Laravel' => ['laravel_session', 'csrf-token" content'],
            'Cloudflare' => ['cdnjs.cloudflare.com', 'cloudflareinsights'],
        ];
        $hay = strtolower($html . ' ' . $gen);
        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($hay, strtolower($needle))) {
                    $tech[] = $label;
                    break;
                }
            }
        }
        return array_values(array_unique($tech));
    }

    private static function suggestMethod(string $path, string $contentType, string $category): string
    {
        if ($category === 'api' || str_contains($contentType, 'json') || preg_match('#/(graphql)(/|$)#i', $path)) {
            return 'GET';
        }
        return 'GET';
    }

    private static function suggestExpectedStatus(?int $statusCode, string $contentType): int
    {
        if ($statusCode !== null && $statusCode >= 100 && $statusCode < 400) {
            return $statusCode;
        }
        if ($statusCode !== null && $statusCode >= 400 && $statusCode < 500) {
            // Still suggest monitoring for that code if intentional, else 200
            return 200;
        }
        return 200;
    }

    private static function preferSlugSource(string $name, string $domainName, string $path): string
    {
        if (preg_match('#^/(?!$)([a-z0-9\-_]+)#i', $path, $m) && !in_array(strtolower($m[1]), ['api', 'v1', 'v2', 'v3', 'index', 'home'], true)) {
            // Prefer brand slug over deep path
            return $name !== '' ? $name : ($domainName . '-' . $m[1]);
        }
        return $name !== '' ? $name : $domainName;
    }

    /** @param list<array<string, mixed>> $jsonLd */
    private static function jsonLdFirst(array $jsonLd, array $fields): ?string
    {
        foreach ($jsonLd as $node) {
            foreach ($fields as $field) {
                if (!empty($node[$field]) && is_string($node[$field])) {
                    $val = self::cleanText($node[$field]);
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
        }
        return null;
    }

    private static function domainLabel(string $host): string
    {
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            return $parts[count($parts) - 2];
        }
        return $host;
    }

    private static function registrableDomain(string $host): string
    {
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            return $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
        }
        return $host;
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return substr($value !== '' ? $value : 'website', 0, 80);
    }

    private static function cleanText(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        // Replace common mojibake / symbol noise
        $text = str_replace(["\u{FFFD}", '�'], '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
}
