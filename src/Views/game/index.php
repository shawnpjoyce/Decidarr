<?php
use App\Core\Security;

$errors = $errors ?? [];
$movie = $movie ?? null;
$configReady = (bool) ($configReady ?? false);
$recentUploads = $recentUploads ?? [];
$recentWatched = $recentWatched ?? [];

$posterPath = is_array($movie) && !empty($movie['thumb'])
    ? '/poster?path=' . rawurlencode((string) $movie['thumb'])
    : '';
$excludedRatingKeys = array_values(array_filter(
    array_map(static fn (array $recentMovie): string => (string) ($recentMovie['ratingKey'] ?? ''), $recentWatched),
    static fn (string $ratingKey): bool => $ratingKey !== ''
));

$title = is_array($movie) ? (string) $movie['title'] : '';
$titleLetters = $title !== '' ? preg_split('//u', $title, -1, PREG_SPLIT_NO_EMPTY) : []; /* I need to figure something out here. I really like the text individual for the bounce effect, but long titles suffer.*/
$buttonLabel = is_array($movie) ? 'Spin Again?' : 'Spin'; /* Button DOM response after user press */
?>

                        <section class="game-board">
                            <section class="prize-stage" aria-live="polite">
                                <div class="prize-burst" aria-hidden="true"></div>

                                <?php if ($errors !== []): ?>
                                    <div class="alert" role="alert">
                                        <?php foreach ($errors as $error): ?>
                                            <p><?= Security::escape((string) $error) ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (is_array($movie)): ?>
                                    <article class="movie-card is-revealed" data-reveal-card>
                                        <div class="poster-prize">
                                            <div class="poster-frame">
                                                <?php if ($posterPath !== ''): ?>
                                                    <img src="<?= Security::escape($posterPath) ?>" alt="<?= Security::escape($title) ?> poster">
                                                <?php else: ?>
                                                    <div class="poster-placeholder">?</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="movie-details">
                                            <p class="result-label">decidarr</p>
                                            <h2 class="playful-title" aria-label="<?= Security::escape($title) ?>">
                                                <?php foreach ($titleLetters as $letter): ?>
                                                    <span aria-hidden="true"><?= $letter === ' ' ? '&nbsp;' : Security::escape($letter) ?></span>
                                                <?php endforeach; ?>
                                            </h2>
                                            <div class="movie-meta">
                                                <?php if (!empty($movie['year'])): ?><span><?= Security::escape((string) $movie['year']) ?></span><?php endif; ?>
                                                <?php if (!empty($movie['contentRating'])): ?><span><?= Security::escape((string) $movie['contentRating']) ?></span><?php endif; ?>
                                                <?php if (!empty($movie['libraryTitle'])): ?><span><?= Security::escape((string) $movie['libraryTitle']) ?></span><?php endif; ?>
                                            </div>
                                            
                                        </div>
                                    </article>
                                <?php endif; ?>

                                <div class="picker-stack">
                                    <p class="result-label">decidarr</p>
                                    <button
                                        class="pull-button"
                                        type="button"
                                        data-random-movie
                                        data-csrf-token="<?= Security::escape(Security::csrfToken()) ?>"
                                        data-excluded-rating-keys="<?= Security::escape(implode(',', $excludedRatingKeys)) ?>"
                                        <?= $configReady ? '' : 'disabled' ?>
                                    >
                                        <span><?= Security::escape($buttonLabel) ?></span>
                                    </button>

                                    <?php if ($recentUploads !== [] || $recentWatched !== []): ?>
                                        <section class="recent-shelves" aria-label="Recent movies">
                                            <?php if ($recentUploads !== []): ?>
                                                <section class="recent-shelf recent-uploads" aria-label="Recently added movies">
                                                    <p class="recent-label">Recently Uploaded</p>
                                                    <div class="recent-poster-row">
                                                        <?php foreach ($recentUploads as $recentMovie): ?>
                                                            <?php
                                                            $recentPoster = !empty($recentMovie['thumb'])
                                                                ? '/poster?path=' . rawurlencode((string) $recentMovie['thumb'])
                                                                : '';
                                                            ?>
                                                            <article class="recent-poster">
                                                                <?php if ($recentPoster !== ''): ?>
                                                                    <img src="<?= Security::escape($recentPoster) ?>" alt="<?= Security::escape((string) $recentMovie['title']) ?> poster">
                                                                <?php else: ?>
                                                                    <span><?= Security::escape(substr((string) $recentMovie['title'], 0, 1)) ?></span>
                                                                <?php endif; ?>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </section>
                                            <?php endif; ?>

                                            <?php if ($recentWatched !== []): ?>
                                                <section class="recent-shelf recent-watched" aria-label="Recently watched movies">
                                                    <p class="recent-label">Recently Watched</p>
                                                    <div class="recent-poster-row">
                                                        <?php foreach ($recentWatched as $recentMovie): ?>
                                                            <?php
                                                            $recentPoster = !empty($recentMovie['thumb'])
                                                                ? '/poster?path=' . rawurlencode((string) $recentMovie['thumb'])
                                                                : '';
                                                            ?>
                                                            <article class="recent-poster">
                                                                <?php if ($recentPoster !== ''): ?>
                                                                    <img src="<?= Security::escape($recentPoster) ?>" alt="<?= Security::escape((string) $recentMovie['title']) ?> poster">
                                                                <?php else: ?>
                                                                    <span><?= Security::escape(substr((string) $recentMovie['title'], 0, 1)) ?></span>
                                                                <?php endif; ?>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </section>
                                            <?php endif; ?>
                                        </section>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </section>