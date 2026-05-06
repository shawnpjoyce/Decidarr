# Decidarr

May the odds be forever in your favor! A Plex movie roulette app that randomly selects a movie from your library.

## INFO

Decidarrr is a vanilla PHP, SQLite, and JavaScript web app that connects to your Plex server, shows recently uploaded movies, and randomly selects a movie from your library in a random game. It uses Docker, Nginx, PDO, CSRF protection, escaped output, and server-side poster proxying to keep Plex tokens out of browser URLs.

## How to run

- .env configure

```ini
PLEX_SERVER_URL=http://host.docker.internal:32400
PLEX_TOKEN=
PLEX_LIBRARY_SECTION_ID=
```

`PLEX_LIBRARY_SECTION_ID` is optional. Leave it blank to pick from any movie library.

## Storage / DB
- SQLite placed in '/storage'.
- Only stores recent movies selected.
- User credientials or tokens are stored in .env-- SQLite is schema only.