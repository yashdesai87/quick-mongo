# Quick Mongo

A read-only MongoDB browser in a single PHP folder. No Composer, no build step: drop it on any PHP host with the `mongodb` extension and point it at a server.

## Features

- Lists databases with their size on disk
- Lists collections with document count and data size
- Pages through the documents of any collection, sorted by `_id` ascending or descending
- Shows a document as highlighted JSON or as a collapsible tree
- Downloads files from any GridFS bucket (open a document in a `*.files` collection)
- Renders timestamps in a timezone picked in the header (defaults to the browser timezone)

## Screenshots

Collections in a database, with document count and size:

![Collections](docs/screenshots/collections.png)

Documents in a collection, sorted by `_id`, with a preview of each document. Compound keys are shown as JSON:

![Documents](docs/screenshots/documents.png)

A single document as highlighted JSON, with a tree view one click away:

![Document](docs/screenshots/document.png)

## Requirements

- PHP 8.1 or newer with the `mongodb` extension 1.16 or newer (`pecl install mongodb`)
- A MongoDB server the PHP driver can reach
- Apache 2.4 with `mod_rewrite`, nginx with php-fpm, or PHP's built-in server for local use

The folder itself never has to be writable, and the app never has to sit at the document root. A copy at `/tools/quick-mongo/` works as it is, because every link, asset and form in it is relative.

## Setup

### 1. Configuration, if you need any

Against a MongoDB on localhost with no authentication there is nothing to configure, so skip to step 2. Otherwise copy `.env.example` to `.env` and set:

- `MONGO_URI`: connection string, default `mongodb://localhost:27017`
- `APP_DEBUG`: `true` shows exception details on error pages, keep it `false` anywhere shared

Real environment variables of the same name win over the file, which is how the Docker image is configured. Under php-fpm they reach PHP only if the pool passes them on, since `clear_env = yes` is the default, so either set them with `env[MONGO_URI] = ...` in the pool config or use `.env` and forget about it.

### 2. Serve the folder

**Apache 2.4.** Copy the folder into the document root or any subdirectory of it, and make sure `.htaccess` is actually read:

```
<Directory /var/www/html>
    AllowOverride All
</Directory>
```

This matters. Debian and Ubuntu ship `AllowOverride None` for the document root, and with that the `.htaccess` in this folder is ignored in full, so there is no routing, no deny rules, and `.env` is downloadable. `mod_rewrite` has to be on (`a2enmod rewrite`). `mod_headers` and `mod_expires` are used when present and skipped when not.

**PHP built-in server.** From inside the folder:

```
php -S localhost:8080 router.php
```

`router.php` applies the same deny rules, because the built-in server ignores `.htaccess`.

**nginx with php-fpm.** There is no `.htaccess` to fall back on, so the server block carries the same policy: serve `assets/`, refuse dotfiles and internals, send everything else to `index.php`.

```nginx
server {
    listen 80;
    server_name mongo.example.com;
    root /var/www/quick-mongo;

    # Certificate renewal: ^~ wins over the dotfile regex below
    location ^~ /.well-known/ { }

    # Assets are the only thing served from disk
    location ^~ /assets/ { try_files $uri =404; }

    # Application internals and dotfiles are never served
    location ~ ^/(config|core|views)(/|$) { return 403; }
    location ~ /\.                        { return 403; }

    # Everything else is the front controller
    location / { rewrite ^ /index.php last; }

    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }
}
```

To run it under a subdirectory instead, put the same four `location` blocks under that prefix and set `root` so the prefix resolves into the folder.

### 3. Open the URL in a browser

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
```

`MONGO_URI` points at the Mongo service name on the compose network. `APP_DEBUG` is optional and means the same as in `.env`.

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

jQuery and Prism load from public CDNs, pinned to exact versions with subresource integrity hashes, so a modified file is refused rather than run. Every other asset is local. Somewhere with no route to those CDNs the page still renders and every value is readable, but the parts built on jQuery stop working: the database selector, the tree view toggles and the timezone control.

## Building the image

Multi-platform pushes need a `docker-container` builder, created once:

```
docker buildx create --name quick-mongo --driver docker-container --use
```

Each release is one build that pushes both platforms under a version tag and `latest`:

```
docker buildx build --builder quick-mongo --platform linux/amd64,linux/arm64 \
  -t codeboxindia/quick-mongo:<version> -t codeboxindia/quick-mongo:latest --push .
```

## License

Apache License 2.0. See `LICENSE`.
