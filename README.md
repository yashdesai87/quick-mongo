# Quick Mongo

A read-only MongoDB browser in a single PHP folder. No Composer, no build step: drop it on any PHP host with the `mongodb` extension and point it at a server.

## Features

- Lists databases with their size on disk
- Lists collections with document count and data size
- Pages through the documents of any collection, sorted by `_id` ascending or descending
- Shows a document as highlighted JSON or as a collapsible tree
- Downloads files from any GridFS bucket (open a document in a `*.files` collection)
- Renders timestamps in a timezone picked in the header (defaults to the browser timezone)

## Requirements

- PHP 8.1 or newer with the `mongodb` extension (`pecl install mongodb`)
- A MongoDB server the PHP driver can reach
- Apache 2.4 with `mod_rewrite`, or PHP's built-in server for local use

## Setup

1. Copy `.env.example` to `.env` and set:
   - `MONGO_URI`: connection string, default `mongodb://localhost:27017`
   - `MONGO_DB`: optional database to always list, even before it has data
   - `APP_DEBUG`: `true` shows exception details on error pages, keep it `false` anywhere shared

   Real environment variables with the same names override the file, so containers can skip `.env` entirely.

2. Serve the folder:
   - Apache: point a virtual host or a subdirectory at the folder. `.htaccess` handles routing and denies `config/`, `core/`, `views/` and dotfiles.
   - Built-in server: run `php -S localhost:8080 router.php` from the folder. `router.php` applies the same deny rules because the built-in server ignores `.htaccess`.
   - nginx: route every request to `index.php` and deny the same paths in the server block.

3. Open the URL in a browser.

## Run with Docker

The image `codeboxindia/quick-mongo` is built for `linux/amd64` and `linux/arm64`. The usual way to use it is as one more service in your project's `docker-compose.yml`, next to your Mongo service:

```yaml
services:
  mongo:
    image: mongo:7

  quick-mongo:
    image: codeboxindia/quick-mongo
    ports:
      - "8081:80"
    environment:
      MONGO_URI: mongodb://mongo:27017
      MONGO_DB: myapp
```

`MONGO_URI` points at the Mongo service name on the compose network. `MONGO_DB` and `APP_DEBUG` are optional and mean the same as in `.env`.

For a Mongo running on the host itself:

```
docker run --rm -p 8081:80 -e MONGO_URI=mongodb://host.docker.internal:27017 codeboxindia/quick-mongo
```

On Linux add `--add-host=host.docker.internal:host-gateway` so that hostname resolves.

## Security

- Read-only by design: the code issues only list, find, count and collStats commands. There is no write, update or delete path.
- Actions are whitelisted; database and collection names are validated before they reach the driver.
- Every value is HTML-escaped on output.
- 100 requests per minute per session.
- There is no authentication built in. Put it behind HTTP basic auth, a VPN or a firewall before exposing anything other than a local development database.

jQuery and Prism load from public CDNs; every other asset is local.

## License

Apache License 2.0. See `LICENSE`.
