# Production

One DigitalOcean droplet (Ubuntu 24.04, 2 GB) behind Cloudflare. `provision.yml` sets up the host, and `deploy.yml` ships a git ref as a new release.

## First run

1. Put the droplet IP in `inventory.yml`. Set `app_hostname` in `group_vars/all.yml`. `site_name` and `site_description` are only first-run defaults: after install, the name, tagline, bio, links, accent colour and logo are edited in admin under Site settings.
2. Copy `secrets.example.yml` to `secrets.yml` (gitignored) and fill it in. Keep a copy outside the repo.
3. Run `make provision`, then `make deploy` (or `make deploy REF=<branch>`).
4. Create the first user before the site is reachable: until a user exists, anyone who opens `/install` can claim the site. Right after the first deploy, and before adding the Cloudflare DNS record, run `ssh -t <droplet> sudo -u goku php /srv/goku/current/bin/console app:user:create <email>`. The password is prompted for, so it stays out of shell history. Once that user exists, `/install` redirects to the login page. If the DNS record is already live, open `https://<app_hostname>/install` immediately instead.

## Cloudflare

- DNS: an A record for `app_hostname`, proxied (orange cloud).
- SSL/TLS: Full (strict). The origin certificate in `secrets.yml` is valid only behind Cloudflare.
- Cache Rule, so public HTML is cached at the edge: match hostname equals `app_hostname`, set it "Eligible for cache", and set Edge TTL to "Use cache-control header if present". The app sends `s-maxage=300` on public pages for anonymous visitors only, and logged-in responses are private. A published chapter therefore shows up within 5 minutes, with no purge needed. Published media is held at the edge for 1 hour (`s-maxage=3600`), then revalidated with a cheap 304, so an unpublished chapter's images leave the edge within the hour. Browsers that already downloaded them keep their copies.
- Uploads: Cloudflare Free cuts request bodies at 100 MB, while the origin accepts 200 MB. Upload bigger archives through the origin, or split them.

## Firewall

Ports 80 and 443 are open to Cloudflare's IP ranges only, fetched at provision time. Re-run `make provision` when Cloudflare changes them.
