# TLStore live server setup on Linode

This guide provisions a first production server for the Laravel API and Flutter web client in this repository. The commands assume an Ubuntu 24.04 LTS Compute Instance and a domain name you control.

## Recommendation: choose an OS image

In Linode Cloud Manager, create a **Compute Instance** and choose **Ubuntu 24.04 LTS** under **OS Images**.

For this project, start from the OS image and install the runtime yourself:

- **OS image — recommended.** It gives you control over the PHP 8.3 runtime Laravel 13 requires, Nginx configuration, Redis, queue workers, and the Flutter web build served through Laravel.
- **Quick Deploy App — not my first choice here.** Linode's LAMP Quick Deploy App currently uses Ubuntu 24.04 and installs Apache, MySQL, and PHP, but the project needs a deliberate production layout and has API, queued work, Firebase, payment, and Flutter web configuration. Starting with a plain OS avoids inheriting a web stack layout you may later need to replace. It is still reasonable if you specifically want Apache and are comfortable adapting its configuration.
- **StackScript — use later.** A StackScript runs first-boot provisioning. It is useful after these steps have been proven and you want to repeat the same server setup. For the first live deployment, manual setup is easier to inspect and troubleshoot. Linode's current secure setup guide also describes its Metadata service/cloud-init as the newer option for repeatable provisioning.

Linode documents the LAMP Quick Deploy compatibility and installed stack in its [LAMP Stack guide](https://www.linode.com/docs/marketplace-docs/guides/lamp-stack/). Its staff describe Quick Deploy as a curated preinstalled application and StackScripts as custom first-boot provisioning in this [Quick Deploy versus StackScripts discussion](https://www.linode.com/community/questions/40466/stackscripts-versus-quick-deploy).

## 1. Create the Compute Instance

In Cloud Manager:

1. Select **Create → Compute Instance**.
2. Under **Images**, select **Ubuntu 24.04 LTS**.
3. Choose a region close to most of your users. Compare latency from your users' countries, and keep the app server and database in the same region.
4. Choose a Shared CPU plan. **4 GB RAM is a sensible starting point** for Laravel, MySQL, Redis, and queue workers on one host. A 2 GB plan can work at low traffic but leaves less room for database and background jobs. Increase the plan as traffic and memory use grow.
5. Set a recognizable label, such as `tlstore-production`, and optionally add a `production` tag.
6. Add your SSH public key in Cloud Manager. If you do not have one on Windows PowerShell, generate it locally:

   ```powershell
   ssh-keygen -t ed25519 -C "tlstore-linode"
   Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub
   ```

   Add the displayed `.pub` key to Cloud Manager. Keep the private `id_ed25519` file on your computer and never paste it into the server or a repository.
7. Set a strong unique root password as the initial recovery method. Use SSH keys for routine access.
8. Enable Linode Backups if they fit your recovery requirements. A server backup is not a substitute for separate database backups.
9. Create the instance and wait for it to finish provisioning. Note its public IPv4 address.

## 2. Point your domain to the server

At your DNS provider, create an `A` record for the domain or subdomain that will host the app, pointing to the Linode IPv4 address. For example:

```text
app.example.com  A  <LINODE_IPV4>
```

If you add an `AAAA` record, make sure IPv6 is configured and reachable on the server too. Otherwise, do not publish an `AAAA` record yet. DNS can take some time to update.

This guide uses one hostname for both the Flutter site and API. The Flutter client can then use `https://app.example.com/api/v1/` as `API_BASE_URL`.

## 3. Log in, update Ubuntu, and add a deploy user

From PowerShell, connect using the root password/key configured at creation:

```powershell
ssh root@<LINODE_IPV4>
```

On the server:

```bash
apt update && apt upgrade -y
adduser deploy
usermod -aG sudo deploy
```

Add your SSH public key for `deploy` (the key was added to the Linode account during instance setup):

```bash
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
nano /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
```

Paste the **public** key as one line and save. Open a second PowerShell window and verify `ssh deploy@<LINODE_IPV4>` works before closing the root session. After confirming access, harden SSH to disallow password and root login. Follow Linode's current [Set up and secure a Compute Instance](https://www.linode.com/docs/products/compute/compute-instances/guides/set-up-and-secure/) steps carefully; keep a working SSH session open while validating SSH configuration.

## 4. Configure the firewall

Use either Linode Cloud Firewall or UFW as the primary firewall. For a straightforward first setup, UFW can allow SSH and web traffic:

```bash
sudo apt install -y ufw
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status verbose
```

Do not allow public inbound MySQL (3306) or Redis (6379). Keep those services bound to localhost on this single-server setup. Linode warns that overly restrictive firewall rules can cut off SSH; its [firewall guidance](https://www.linode.com/docs/guides/configure-firewall-with-ufw/) explains the setup.

## 5. Install the web and PHP runtime

Ubuntu 24.04 provides PHP 8.3, which satisfies this repository's Composer requirement (`php: ^8.3`). Install Nginx, PHP-FPM and common Laravel extensions, MySQL, Redis, Git, and Composer:

```bash
sudo apt install -y nginx mysql-server redis-server git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-mbstring \
  php8.3-xml php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl php8.3-redis \
  composer
```

Check PHP and services:

```bash
php -v
sudo systemctl enable --now nginx mysql redis-server php8.3-fpm
sudo systemctl status nginx mysql redis-server php8.3-fpm --no-pager
```

Run MySQL's initial security setup, then create a production database and restricted database user. Use a strong generated password and keep it in your password manager:

```bash
sudo mysql_secure_installation
sudo mysql
```

In the MySQL prompt (replace the placeholders; do not use angle brackets literally):

```sql
CREATE DATABASE tlstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tlstore_app'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD';
GRANT ALL PRIVILEGES ON tlstore.* TO 'tlstore_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 6. Add the application code

Use your private Git repository or another authenticated deployment method. Do not upload `.env`, private keys, Firebase service-account files, or payment credentials into Git.

```bash
sudo mkdir -p /var/www/tlstore
sudo chown deploy:www-data /var/www/tlstore
git clone <YOUR_PRIVATE_REPOSITORY_URL> /var/www/tlstore
cd /var/www/tlstore
```

If the clone directory already exists, update it with your normal release process rather than cloning over it. Install production Composer dependencies:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Create the production `.env` on the server. Start from `.env.example`, but enter **live values** and keep the file outside source control:

```bash
cp .env.example .env
nano .env
```

At minimum configure these values and review the other integration settings already used by the project:

```dotenv
APP_NAME="TLStore"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tlstore
DB_USERNAME=tlstore_app
DB_PASSWORD=<database-password>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Copy and configure the remaining production values from the names in `.env.example` and the application's integration configuration: SMTP/Resend/Mailgun/Mailjet, payment gateways (including VTPass and BACHS), social sign-in, Firebase Cloud Messaging, account validation, and any webhook secrets. Use the provider's live credentials, not test credentials. Do not put secrets in commands, screenshots, or support messages.

Generate `APP_KEY` once on the production server and do not regenerate it during routine deployments:

```bash
php artisan key:generate
```

The Firebase service-account JSON must be placed at the path configured by `FCM_SERVICE_ACCOUNT_PATH`, with read access limited to the application user. Do not put it in a public directory. Keep Firebase web configuration and the web messaging service worker in the Flutter web build as required by the project's existing Firebase setup.

Lock down permissions and create Laravel's public storage link:

```bash
sudo chown -R deploy:www-data /var/www/tlstore
sudo find /var/www/tlstore -type f -exec chmod 644 {} \;
sudo find /var/www/tlstore -type d -exec chmod 755 {} \;
sudo chmod 640 /var/www/tlstore/.env
sudo chmod -R ug+rwx /var/www/tlstore/storage /var/www/tlstore/bootstrap/cache
php artisan storage:link
```

For a Firebase service-account file or other private file stored under the project, set its owner to `deploy:www-data` and its mode to `640` as well. Keep private credentials outside `public/`.

## 7. Build the Flutter web client

The project serves Flutter's compiled web files from `public/web`. Build the Flutter client with the **live HTTPS API URL**. This build can be run on your development computer or in CI; Flutter does not need to be installed on the production server.

From the repository root on a machine with Flutter installed:

```powershell
cd review
flutter pub get
flutter build web --release --base-href /web/ --dart-define=API_BASE_URL=https://app.example.com/api/v1/
```

Deploy the contents of `review/build/web/` to `/var/www/tlstore/public/web/` (preserving the `web` directory level). For example, from PowerShell with `scp` available:

```powershell
ssh deploy@<LINODE_IPV4> "sudo mkdir -p /var/www/tlstore/public/web && sudo chown -R deploy:www-data /var/www/tlstore/public/web"
scp -r .\build\web\* deploy@<LINODE_IPV4>:/var/www/tlstore/public/web/
```

Create `/var/www/tlstore/public/web` first and ensure the deploy user's SSH key is accepted. If using a different build machine, copy that build output from its actual location. Rebuild and redeploy this web folder whenever Flutter code or the API base URL changes.

## 8. Configure Nginx and TLS

Create an Nginx site. Replace `app.example.com` with your hostname:

```bash
sudo nano /etc/nginx/sites-available/tlstore
```

Use this initial HTTP configuration:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name app.example.com;
    root /var/www/tlstore/public;
    index index.php;

    client_max_body_size 20m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Enable and verify it:

```bash
sudo ln -s /etc/nginx/sites-available/tlstore /etc/nginx/sites-enabled/tlstore
sudo nginx -t
sudo systemctl reload nginx
```

After DNS resolves to this server, enable HTTPS with Let's Encrypt:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d app.example.com
```

Choose the redirect-to-HTTPS option. Check that certificate renewal is enabled with `sudo systemctl status certbot.timer`.

The repository's `routes/web.php` serves the Flutter entry point at `/` and its client-side routes, while Laravel handles `/api/...`; the static Flutter files stay under `/web/`. Test `/`, `/web/`, `/api/v1/` and a Flutter page refresh after deployment. If the server has Flutter files but root gives a Laravel 404, check that the Laravel route file from this release is deployed and that `public/web/index.html` exists.

## 9. Run the production database migration and optimize Laravel

Before migrating, make a database backup. Then on the server:

```bash
cd /var/www/tlstore
php artisan migrate --force
php artisan optimize
```

Do **not** run `migrate:fresh` on production; it drops existing tables. Do **not** run the demo seeders in production. If production needs initial settings, insert only reviewed production configuration through the intended admin/setup process.

Set production ownership for writable paths after deployment:

```bash
sudo chown -R deploy:www-data /var/www/tlstore
sudo chmod -R ug+rwx /var/www/tlstore/storage /var/www/tlstore/bootstrap/cache
```

Laravel's web process runs as `www-data`; ensure it can read `.env`, application files and Firebase credentials while only the deploy/admin account can edit secret files. Keep `.env` permission restricted (for example `chmod 640 .env` with a group that PHP-FPM can read).

## 10. Keep queues and scheduled commands running

This application uses Laravel queues and scheduled commands. Use Supervisor to keep a queue worker alive:

```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/tlstore-worker.conf
```

Example worker configuration:

```ini
[program:tlstore-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/tlstore/artisan queue:work redis --sleep=3 --tries=3 --timeout=120
directory=/var/www/tlstore
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/tlstore/storage/logs/worker.log
stopwaitsecs=130
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Add Laravel's scheduler to the `www-data` crontab (only once):

```bash
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/tlstore && php artisan schedule:run >> /dev/null 2>&1
```

Confirm the project's scheduled commands are registered with `php artisan schedule:list` and check logs after the first scheduled run.

## 11. Final launch checks

- Confirm `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` uses HTTPS, and all live gateway/mail/Firebase credentials are correct.
- Confirm DNS, TLS, `/`, `/api/v1/`, Flutter routes, uploads, and the Firebase messaging service worker load over HTTPS.
- Confirm `storage` is writable by PHP-FPM and files are not exposed from secret locations.
- Confirm the queue worker and scheduler are active. Inspect `storage/logs/laravel.log` and the Supervisor worker log.
- Make a small test top-up/payment only with provider-approved live test procedures, and verify gateway callbacks/webhooks reach the HTTPS domain.
- Verify the Flutter app's published API base URL. If mobile apps should use this server too, update their configured API URL and publish new Android/iOS builds as appropriate.
- Configure automated database backups and periodically verify that a backup can be restored.

## Routine deployment outline

For each backend release, back up the database, deploy reviewed source, run `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`, run `php artisan migrate --force`, then `php artisan optimize`, and restart queue workers with `php artisan queue:restart`. Deploy a newly built `review/build/web/` to `public/web/` when the Flutter client changes. Never overwrite production `.env`, uploads, Firebase service credentials, or user data during a release.

## Linode references

- [Create a Compute Instance](https://www.linode.com/docs/products/compute/compute-instances/guides/create/)
- [Set up and secure a Compute Instance](https://www.linode.com/docs/products/compute/compute-instances/guides/set-up-and-secure/)
- [Configure a firewall with UFW](https://www.linode.com/docs/guides/configure-firewall-with-ufw/)
- [LAMP Stack Quick Deploy App](https://www.linode.com/docs/marketplace-docs/guides/lamp-stack/)
- [Quick Deploy versus StackScripts](https://www.linode.com/community/questions/40466/stackscripts-versus-quick-deploy)
