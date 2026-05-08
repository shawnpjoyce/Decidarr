<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class PickHistory
{
    public function __construct(private readonly PDO $pdo)
    {
    }
    // I added this to prevent selection of the same movie id.
    // feature later would be to manage watched history for any possible errors.
    public function latest(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, title, year, library_title, rating_key, thumb_path, picked_at
             FROM pick_history
             ORDER BY datetime(picked_at) DESC, id DESC
             LIMIT 8'
        );

        return $statement->fetchAll();
    }

    public function record(array $movie): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO pick_history (title, year, library_title, rating_key, thumb_path, picked_at)
             VALUES (:title, :year, :library_title, :rating_key, :thumb_path, CURRENT_TIMESTAMP)'
        );

        $year = isset($movie['year']) ? (int) $movie['year'] : null;
        $statement->bindValue(':title', (string) ($movie['title'] ?? 'Untitled Movie'));
        $statement->bindValue(':year', $year, $year === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':library_title', (string) ($movie['libraryTitle'] ?? 'Movies'));
        $statement->bindValue(':rating_key', (string) ($movie['ratingKey'] ?? ''));
        $statement->bindValue(':thumb_path', (string) ($movie['thumb'] ?? ''));
        $statement->execute();
    }
}