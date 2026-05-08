<?php
declare(strict_types=1);
/*
I need to learn that DocBlock for this so people know what to mess with.
*/
namespace App\Services;

use RuntimeException;
use SimpleXMLElement;

final class PlexClient
{
    private const MAX_XML_BYTES = 12000000;
    private const MAX_IMAGE_BYTES = 9000000;

    private function __construct(
        private readonly string $serverUrl,
        private readonly string $token
    ) {
    }

    public static function fromUserInput(string $serverUrl, string $token): self
    {
        $serverUrl = rtrim(trim($serverUrl), '/');
        $parts = parse_url($serverUrl);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Enter a Plex server URL like http://host.docker.internal:32400.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https Plex server URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Do not include usernames or passwords in the Plex server URL.');
        }

        self::assertHostIsSafe((string) $parts['host']);

        return new self($serverUrl, $token);
    }

    public function serverUrl(): string
    {
        return $this->serverUrl;
    }

    public function movieLibraries(): array
    {
        $xml = $this->requestXml('/library/sections');
        $libraries = [];

        foreach ($xml->Directory ?? [] as $directory) {
            if ((string) ($directory['type'] ?? '') !== 'movie') {
                continue;
            }

            $libraries[] = [
                'key' => (int) $directory['key'],
                'title' => (string) $directory['title'],
            ];
        }

        if ($libraries === []) {
            throw new RuntimeException('No movie libraries were found for that Plex server and token.');
        }

        return $libraries;
    }

    public function randomMovie(?int $sectionId, array $libraries, array $excludedRatingKeys = []): array
    {
        $targetLibraries = $libraries;
        $excludedRatingKeys = array_fill_keys($excludedRatingKeys, true);

        if ($sectionId !== null) {
            $targetLibraries = array_values(array_filter(
                $libraries,
                static fn (array $library): bool => (int) $library['key'] === $sectionId
            ));

            if ($targetLibraries === []) {
                throw new RuntimeException('That movie library section was not found.');
            }
        }

        shuffle($targetLibraries);
        $movies = [];

        foreach ($targetLibraries as $library) {
            $xml = $this->requestXml('/library/sections/' . (int) $library['key'] . '/all', [
                'type' => '1',
                'X-Plex-Container-Start' => '0',
                'X-Plex-Container-Size' => '5000',
            ]);

            foreach ($xml->Video ?? [] as $video) {
                if ((string) ($video['type'] ?? '') !== 'movie') {
                    continue;
                }

                $movie = $this->movieFromVideo($video, (string) $library['title'], (int) $library['key']);

                if ($movie['ratingKey'] !== '' && isset($excludedRatingKeys[$movie['ratingKey']])) {
                    continue;
                }

                $movies[] = $movie;
            }

            if ($movies !== []) {
                break;
            }
        }

        if ($movies === []) {
            throw new RuntimeException('No eligible movies were found outside the recently watched row.');
        }

        return $movies[random_int(0, count($movies) - 1)];
    }

    public function recentMovies(?int $sectionId, int $limit = 8): array
    {
        $limit = max(1, min(12, $limit));
        $path = $sectionId === null
            ? '/library/recentlyAdded'
            : '/library/sections/' . $sectionId . '/recentlyAdded';

        $xml = $this->requestXml($path, [
            'type' => '1',
            'X-Plex-Container-Start' => '0',
            'X-Plex-Container-Size' => (string) $limit,
        ]);

        $movies = [];
        foreach ($xml->Video ?? [] as $video) {
            if ((string) ($video['type'] ?? '') !== 'movie') {
                continue;
            }

            $movies[] = $this->movieFromVideo(
                $video,
                (string) ($video['librarySectionTitle'] ?? 'Movies'),
                (int) ($video['librarySectionID'] ?? ($sectionId ?? 0))
            );

            if (count($movies) >= $limit) {
                break;
            }
        }

        return $movies;
    }

    public function recentlyWatchedMovies(?int $sectionId, int $limit = 8): array
    {
        $limit = max(1, min(12, $limit));
        $scanLimit = max(24, $limit * 3);
        $libraries = $sectionId === null
            ? $this->movieLibraries()
            : [['key' => $sectionId, 'title' => 'Movies']];
        $movies = [];
        $seen = [];

        foreach ($libraries as $library) {
            $xml = $this->requestXml('/library/sections/' . (int) $library['key'] . '/all', [
                'type' => '1',
                'sort' => 'lastViewedAt:desc',
                'X-Plex-Container-Start' => '0',
                'X-Plex-Container-Size' => (string) $scanLimit,
            ]);

            foreach ($xml->Video ?? [] as $video) {
                if ((string) ($video['type'] ?? '') !== 'movie') {
                    continue;
                }

                $lastViewedAt = (int) ($video['lastViewedAt'] ?? 0);
                if ($lastViewedAt <= 0 && (int) ($video['viewCount'] ?? 0) <= 0) {
                    continue;
                }

                $movie = $this->movieFromVideo(
                    $video,
                    (string) ($video['librarySectionTitle'] ?? $library['title'] ?? 'Movies'),
                    (int) ($video['librarySectionID'] ?? $library['key'])
                );

                $key = $movie['ratingKey'] !== ''
                    ? $movie['ratingKey']
                    : strtolower($movie['title'] . '|' . $movie['year']);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $movie['lastViewedAt'] = (string) $lastViewedAt;
                $movies[] = $movie;
            }
        }

        usort(
            $movies,
            static fn (array $left, array $right): int => (int) ($right['lastViewedAt'] ?? 0) <=> (int) ($left['lastViewedAt'] ?? 0)
        );

        return array_slice($movies, 0, $limit);
    }

    public function poster(string $path): array
    {
        if (!str_starts_with($path, '/') || str_contains($path, "\0")) {
            throw new RuntimeException('Invalid poster path.');
        }

        $response = $this->request($path, [], self::MAX_IMAGE_BYTES);
        $contentType = strtolower($response['contentType']);

        if (!str_starts_with($contentType, 'image/')) {
            $contentType = 'image/jpeg';
        }

        return [
            'contentType' => $contentType,
            'body' => $response['body'],
        ];
    }

    private function requestXml(string $path, array $query = []): SimpleXMLElement
    {
        $response = $this->request($path, $query, self::MAX_XML_BYTES);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response['body']);
        libxml_clear_errors();

        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Plex returned a response that could not be read.');
        }

        return $xml;
    }

    private function movieFromVideo(SimpleXMLElement $video, string $libraryTitle, int $libraryKey): array
    {
        return [
            'title' => (string) ($video['title'] ?? 'Untitled Movie'),
            'year' => (string) ($video['year'] ?? ''),
            'summary' => (string) ($video['summary'] ?? ''),
            'tagline' => (string) ($video['tagline'] ?? ''),
            'contentRating' => (string) ($video['contentRating'] ?? ''),
            'rating' => (string) ($video['rating'] ?? ''),
            'audienceRating' => (string) ($video['audienceRating'] ?? ''),
            'duration' => (string) ($video['duration'] ?? ''),
            'thumb' => (string) ($video['thumb'] ?? ''),
            'ratingKey' => (string) ($video['ratingKey'] ?? ''),
            'libraryTitle' => $libraryTitle,
            'libraryKey' => $libraryKey,
        ];
    }

    private function request(string $path, array $query, int $maxBytes): array
    {
        $query['X-Plex-Token'] = $this->token;
        $url = $this->serverUrl . $path . '?' . http_build_query($query);
        $body = '';

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Could not prepare the Plex request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml,image/*',
                'X-Plex-Product: Decidarrr',
                'X-Plex-Version: 1.0',
                'X-Plex-Client-Identifier: decidarrr-local-picker',
            ],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    return 0;
                }

                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($ok === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Plex did not respond successfully. Check the URL, token, and network access.');
        }

        if ($body === '') {
            throw new RuntimeException('Plex returned an empty response.');
        }

        if ($error !== '') {
            throw new RuntimeException('The Plex request was interrupted.');
        }

        return [
            'body' => $body,
            'contentType' => $contentType,
        ];
    }

    private static function assertHostIsSafe(string $host): void
    {
        $lowerHost = strtolower(trim($host, '[]'));
        if (in_array($lowerHost, ['169.254.169.254', 'metadata.google.internal'], true)) {
            throw new RuntimeException('That host is blocked because it targets cloud metadata services.');
        }

        if (filter_var($lowerHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (str_starts_with($lowerHost, 'fe80:')) {
                throw new RuntimeException('IPv6 link-local Plex URLs are blocked.');
            }
            return;
        }

        $records = filter_var($lowerHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? [$lowerHost]
            : gethostbynamel($lowerHost);

        if ($records === false) {
            throw new RuntimeException('That Plex host could not be resolved.');
        }

        foreach ($records as $ip) {
            if (str_starts_with($ip, '169.254.')) {
                throw new RuntimeException('Link-local hosts are blocked.');
            }
        }
    }
}