# Decidarr

May the odds be forever in your favor!

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