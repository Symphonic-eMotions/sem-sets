# Sem Sets

Symfony 6.4-applicatie met Doctrine, Twig, AssetMapper en een Docker development setup op PHP 8.1, MariaDB en Mailpit.

## Starten met Docker

### Vereisten

- Docker
- Docker Compose

### 1. Containers bouwen en starten

```bash
docker compose up -d --build
```

Hiermee start je:

- de Symfony app op PHP 8.1
- MariaDB 10.11
- Mailpit

### 2. PHP dependencies installeren

Na de eerste build moet je Composer dependencies in de app-container installeren:

```bash
docker compose run --rm app composer install
```

### 3. Database migraties uitvoeren

```bash
docker compose run --rm app php bin/console doctrine:migrations:migrate
```

Optioneel kun je fixtures laden:

```bash
docker compose run --rm app php bin/console doctrine:fixtures:load
```

### 4. Applicatie openen

- App: `http://localhost:8000`
- Mailpit UI: `http://localhost:8025`
- MariaDB op host: `127.0.0.1:3307`

De app-container gebruikt intern deze databaseverbinding:

```text
mysql://app:ChangeMe!@database:3306/app?serverVersion=10.11.2-MariaDB&charset=utf8mb4
```

### 5. Handige Docker commando's

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f database
docker compose down
```

## Werken in de app-container

Voor Symfony- of Composer-commando's kun je deze vorm gebruiken:

```bash
docker compose run --rm app php bin/console cache:clear
docker compose run --rm app php bin/console doctrine:migrations:status
docker compose run --rm app php bin/phpunit
```

Een gebruiker aanmaken:

```bash
docker compose run --rm app php bin/console app:create-user
```

## Docker-opzet

De lokale development omgeving bestaat nu uit:

- `app`: custom PHP 8.1 container op basis van `php:8.1-cli`
- `database`: `mariadb:10.11`
- `mailer`: `axllent/mailpit`

De PHP-container bevat in ieder geval:

- Composer
- `pdo_mysql`
- `intl`
- `zip`

## Bestaande lokale env-bestanden

Let op dat `.env.local` nog steeds een lokale host-database kan overschrijven voor native development buiten Docker. Voor de app-container is dat geen probleem, omdat Docker Compose expliciet `DATABASE_URL` aan de container meegeeft.
