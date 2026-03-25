# Deployment

## Servers

| Omgeving | Domein | App-directory |
|---|---|---|
| Productie | cloud.symphonic-emotions.nl | `/home/symphonic/apps/sem-sets` |
| Test | cloudtest.symphonic-emotions.nl | `/home/symphonic/apps/sem-sets-test` |

Beide omgevingen draaien op dezelfde server. SSH-toegang via de alias `cloudtest` (zie `~/.ssh/config`).

---

## Deployen

### Productie

```bash
ssh cloudtest "bash /home/symphonic/apps/sem-sets/deploy.sh"
```

### Test (cloudtest)

```bash
ssh cloudtest "bash /home/symphonic/apps/sem-sets-test/deploy.sh"
```

Het deploy script voert automatisch uit:
1. `git pull origin master`
2. `composer install --no-dev --optimize-autoloader`
3. `php bin/console doctrine:migrations:migrate --no-interaction`
4. `php bin/console cache:clear`

---

## Workflow

De bedoeling is dat nieuwe code eerst op **cloudtest** wordt getest voordat het naar **productie** gaat:

1. Commit en push lokale wijzigingen naar GitHub (`master`)
2. Deploy naar cloudtest: `ssh cloudtest "bash /home/symphonic/apps/sem-sets-test/deploy.sh"`
3. Test op cloudtest.symphonic-emotions.nl
4. Deploy naar productie: `ssh cloudtest "bash /home/symphonic/apps/sem-sets/deploy.sh"`

---

## Server inrichting

### Webroot

De `public_html` van elk domein is een symlink naar de `public/` map van de app:

```
/home/symphonic/domains/cloud.symphonic-emotions.nl/public_html
  -> /home/symphonic/apps/sem-sets/public

/home/symphonic/domains/cloudtest.symphonic-emotions.nl/public_html
  -> /home/symphonic/apps/sem-sets-test/public
```

### Database

| Omgeving | Database |
|---|---|
| Productie | `symphonic_cloud` |
| Test | `symphonic_cloudtest` |

Verbindingsinstellingen staan in `.env.local` per app-directory (niet in git).

### GitHub toegang

De server gebruikt een wachtwoordloze SSH deploy key (`~/.ssh/deploy_sem_sets`) voor toegang tot de GitHub repo. De configuratie staat in `~/.ssh/config`:

```
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/deploy_sem_sets
    IdentitiesOnly yes
```

De publieke sleutel is toegevoegd als Deploy Key op de GitHub repo (`Settings → Deploy keys`).

---

## Eerste deployment (eenmalig)

Deze stappen zijn al uitgevoerd voor beide omgevingen. Documentatie voor het geval een omgeving opnieuw opgezet moet worden.

```bash
# 1. Repo clonen
git clone git@github.com:Symphonic-eMotions/sem-sets.git /home/symphonic/apps/sem-sets

# 2. .env.local aanmaken
cat > /home/symphonic/apps/sem-sets/.env.local << EOF
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL="mysql://symphonic_user:WACHTWOORD@localhost:3306/symphonic_cloud?charset=utf8mb4"
EOF

# 3. Dependencies installeren
cd /home/symphonic/apps/sem-sets
composer install --no-dev --optimize-autoloader

# 4. Database migraties
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Cache
php bin/console cache:clear

# 6. Webroot symlink
ln -s /home/symphonic/apps/sem-sets/public \
      /home/symphonic/domains/cloud.symphonic-emotions.nl/public_html
```

> **Let op:** de `.env.local` bevat het databasewachtwoord en staat niet in git.
