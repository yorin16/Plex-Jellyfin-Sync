# Media Sync Manager

Compare Plex and Jellyfin libraries, then selectively transfer movies to the Jellyfin server.

## One-time server setup (Unraid)

**1. Create the database and user on your existing MySQL container**

```bash
docker exec -it <your-mysql-container-name> mysql -uroot -p
```
```sql
CREATE DATABASE media_sync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'media_sync'@'%' IDENTIFIED BY 'your-password-here';
GRANT ALL PRIVILEGES ON media_sync.* TO 'media_sync'@'%';
FLUSH PRIVILEGES;
EXIT;
```

**2. Clone the repo to Unraid appdata**

```bash
git clone https://github.com/yorin/Plex-Jellyfin-Sync.git /mnt/user/appdata/media-sync
```

**3. Create the env file**

```bash
cp /mnt/user/appdata/media-sync/.env.example /mnt/user/appdata/media-sync/.env
```

Edit `.env` — key values to set:
| Variable | What to put |
|---|---|
| `PLEX_MEDIA_PATH` | Path to your Plex movies on Unraid, e.g. `/mnt/user/Media/Movies` |
| `DOCKER_NETWORK` | Your existing Docker network (from Jenkins config: `hosting_network`) |
| `MYSQL_HOST` | Your existing MySQL container name (e.g. `mysql` or `mariadb`) |
| `DB_PASSWORD` | The password you set in step 1 |

**4. Start the stack**

```bash
cd /mnt/user/appdata/media-sync
docker compose up -d
```

The app runs on port **8085**. Add it to Nginx Proxy Manager like your other sites.

## Using the app

1. **Profiles → New Profile** — create a sync profile (e.g. "RV Sync")
2. **Settings** — add a Source connection (Plex) and Destination connection (Jellyfin)
3. Click **Libraries** on each connection and select the movie library
4. Set up the **Transfer Configuration** (SFTP to the Jellyfin Pi)
5. **Scan Now** — caches both libraries
6. **Compare** — see what's missing, queue movies for transfer

## CI/CD via Jenkins

The included `Jenkinsfile` does:
1. `git pull` the repo into `/mnt/user/appdata/media-sync`
2. `docker compose up --build -d` — Docker handles everything (Vue build, PHP image, migrations)
3. Health check against `/api/dashboard`

No Node.js or Composer needed on the Jenkins machine.

## Architecture

```
nginx:8085    Routes browser traffic: / → frontend, /api → PHP-FPM
frontend      nginx serving the built Vue SPA (built inside Docker)
php           Symfony 8.1 REST API (PHP-FPM)
worker        Symfony Messenger consumer — runs transfers in background
              (all four containers join your existing hosting_network)
              (MySQL is your existing shared container)
```

## Ports

Only **nginx** exposes a port to the host (`APP_PORT`, default `8085`).
All other containers (php, worker, frontend, MySQL) are internal to the Docker network.

## Development

```bash
cd frontend && npm install && npm run dev
# Vite dev server on :5173, proxies /api to localhost:8085
```

## Adding transfer methods

Implement `App\Service\Transfer\TransferDriverInterface` and register in `config/services.yaml`.
