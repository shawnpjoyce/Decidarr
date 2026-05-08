<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\PickHistory;
use App\Services\PlexClient;
use RuntimeException;

final class PlexController extends BaseController
{
    public function index(): void
    {
        $this->renderGame(null);
    }

    public function pick(): void
    {
        $this->requireValidCsrf();

        $serverUrl = $this->plexServerUrl();
        $token = $this->plexToken();
        $sectionId = $this->plexSectionId();
        $errors = $this->validateConnection($serverUrl, $token, $sectionId);

        if ($errors !== []) {
            $this->renderGame(null, $errors);
            return;
        }

        try {
            $client = PlexClient::fromUserInput($serverUrl, $token);
            $libraries = $client->movieLibraries();
            $excludedRatingKeys = $this->excludedRatingKeys();

            if ($excludedRatingKeys === []) {
                $excludedRatingKeys = $this->recentlyWatchedRatingKeys($client, $sectionId);
            }

            $movie = $client->randomMovie(
                $sectionId === '' ? null : (int) $sectionId,
                $libraries,
                $excludedRatingKeys
            );

            $_SESSION['plex_server_url'] = $client->serverUrl();
            Security::rotateCsrf();

            (new PickHistory($this->database->pdo()))->record($movie);

            $this->renderGame($movie, [], $libraries);
        } catch (RuntimeException $exception) {
            $this->renderGame(null, [$exception->getMessage()]);
        }
    }

    public function poster(): void
    {
        $path = $this->request->input('path');
        $serverUrl = $_SESSION['plex_server_url'] ?? $this->plexServerUrl();
        $token = $this->plexToken();

        if (!is_string($serverUrl) || !is_string($token) || $path === '' || !str_starts_with($path, '/')) {
            http_response_code(404);
            return;
        }

        try {
            $image = PlexClient::fromUserInput($serverUrl, $token)->poster($path);
            header('Content-Type: ' . $image['contentType']);
            header('Cache-Control: private, max-age=300');
            echo $image['body'];
        } catch (RuntimeException) {
            http_response_code(404);
        }
    }

    public function reset(): void
    {
        $this->requireValidCsrf();

        unset($_SESSION['plex_server_url']);
        Security::rotateCsrf();
        $this->redirect('/');
    }

    private function renderGame(?array $movie, array $errors = [], array $libraries = []): void
    {
        $history = (new PickHistory($this->database->pdo()))->latest();
        $serverUrl = $this->plexServerUrl();
        $token = $this->plexToken();
        $sectionId = $this->plexSectionId();
        $configReady = $serverUrl !== '' && $token !== '';
        $recentUploads = $movie === null && $configReady
            ? $this->recentUploads($serverUrl, $token, $sectionId)
            : [];
        $recentWatched = $movie === null && $configReady
            ? $this->recentlyWatched($serverUrl, $token, $sectionId)
            : [];

        $this->view('game/index', [
            'movie' => $movie,
            'history' => $history,
            'errors' => $errors,
            'libraries' => $libraries,
            'configReady' => $configReady,
            'configuredServerUrl' => $serverUrl,
            'configuredSectionId' => $sectionId,
            'recentUploads' => $recentUploads,
            'recentWatched' => $recentWatched,
        ]);
    }

    private function recentUploads(string $serverUrl, string $token, string $sectionId): array
    {
        if ($this->validateConnection($serverUrl, $token, $sectionId) !== []) {
            return [];
        }

        try {
            return PlexClient::fromUserInput($serverUrl, $token)
                ->recentMovies($sectionId === '' ? null : (int) $sectionId, 8);
        } catch (RuntimeException) {
            return [];
        }
    }

    private function recentlyWatched(string $serverUrl, string $token, string $sectionId): array
    {
        if ($this->validateConnection($serverUrl, $token, $sectionId) !== []) {
            return [];
        }

        try {
            return PlexClient::fromUserInput($serverUrl, $token)
                ->recentlyWatchedMovies($sectionId === '' ? null : (int) $sectionId, 8);
        } catch (RuntimeException) {
            return [];
        }
    }

    private function recentlyWatchedRatingKeys(PlexClient $client, string $sectionId): array
    {
        try {
            $movies = $client->recentlyWatchedMovies($sectionId === '' ? null : (int) $sectionId, 8);
        } catch (RuntimeException) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (array $movie): string => (string) ($movie['ratingKey'] ?? ''), $movies),
            static fn (string $ratingKey): bool => $ratingKey !== ''
        ));
    }

    private function excludedRatingKeys(): array
    {
        $keys = preg_split('/\s*,\s*/', $this->request->post('exclude_rating_keys'), -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($keys)) {
            return [];
        }

        $normalized = [];
        foreach ($keys as $key) {
            $key = trim($key);
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key) !== 1) {
                continue;
            }

            $normalized[$key] = true;

            if (count($normalized) >= 12) {
                break;
            }
        }

        return array_keys($normalized);
    }

    private function plexServerUrl(): string
    {
        return trim((string) (getenv('PLEX_SERVER_URL') ?: ''));
    }

    private function plexToken(): string
    {
        return trim((string) (getenv('PLEX_TOKEN') ?: ''));
    }

    private function plexSectionId(): string
    {
        return trim((string) (getenv('PLEX_LIBRARY_SECTION_ID') ?: ''));
    }

    private function validateConnection(string $serverUrl, string $token, string $sectionId): array
    {
        $errors = [];

        if ($serverUrl === '') {
            $errors[] = 'Set PLEX_SERVER_URL in .env.';
        }

        if ($token === '' || strlen($token) > 256 || !preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            $errors[] = 'Set a valid PLEX_TOKEN in .env.';
        }

        if ($sectionId !== '' && filter_var($sectionId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors[] = 'Library section must be a positive number.';
        }

        return $errors;
    }
}