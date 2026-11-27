# Web Server Hardening — Document Root

**Related:** `ERR-01` in [`project-errors.md`](./project-errors.md), Phase 0 of
[`remediation-roadmap.md`](./remediation-roadmap.md).

The document root is `public/`. Only two files there are meant to execute PHP:

| File | Purpose |
|---|---|
| `public/index.php` | Laravel front controller — every application request. |
| `public/redirect.php` | Thin bridge that sends `?ref=`/`?path=` links to the web app. |

Anything else (`adminer.php`, `_2KcfSjmX.php`, `phpinfo.php`, a dropped web shell, a
forgotten export script) must be **unreachable**, not merely uncommitted. The Adminer
4.8.1 file that used to sit in this repository was reachable over plain HTTP, outside
Laravel's middleware stack, which is why a single stray upload used to mean full database
access.

Three layers now apply:

1. `.gitignore` refuses to track any `public/*.php` other than `index.php` and
   `redirect.php`, so the file cannot come back through a commit.
2. `public/.htaccess` blocks every other PHP-like filename for Apache (rewrite rule,
   applied before the front-controller fallback; `403` is returned).
3. The web-server configuration below applies the same rule at the edge. Use it even if
   Apache is not the production server — the `.htaccess` file is ignored by nginx and by
   Caddy/FrankenPHP.

## Caddy / FrankenPHP

```caddyfile
example.com {
    root * /srv/tlstore/public
    encode zstd gzip

    # `route` keeps these directives in the written order: the block below runs
    # before php_server, so a stray .php file never reaches the interpreter.
    route {
        @forbiddenPhp {
            path *.php *.phtml *.phar *.pht *.php7 *.php8
            not path /index.php /redirect.php
        }
        respond @forbiddenPhp 404

        php_server
    }
}
```

## nginx + PHP-FPM

Regex locations are evaluated in the order they appear, so the allow rule must come
first:

```nginx
root /srv/tlstore/public;
index index.php;

# Only the front controller and the gateway bridge may execute PHP.
location ~ ^/(index|redirect)\.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

# Everything else that looks like PHP is refused outright.
location ~ \.(php[0-9]?|phtml|phar|pht|inc)$ {
    return 403;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Apache

`public/.htaccess` already contains the rule. Ensure the vhost allows it:

```apache
<Directory /srv/tlstore/public>
    AllowOverride All
    Require all granted
</Directory>
```

If `AllowOverride` is disabled in production, copy the rewrite block from
`public/.htaccess` into the vhost `<Directory>` section instead — never drop it.

## Deployment checklist

- [ ] `public/` contains exactly `index.php` and `redirect.php` plus static assets
      (`git ls-files public | grep -E '\.(php[0-9]?|phtml|phar|inc)$'`).
- [ ] The edge rule above is active: requesting `/<anything-else>.php` returns 403/404,
      not a PHP response.
- [ ] Writable paths are limited to `storage/` and `bootstrap/cache/`.
- [ ] `.env` is outside the repository and never inside `public/`.
