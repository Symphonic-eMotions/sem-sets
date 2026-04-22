# Gebruikersbeheer

## Nieuwe gebruiker aanmaken

```bash
php bin/console app:create-user
```

Het commando stelt je interactief de volgende vragen:

| Stap | Invoer | Validatie |
|------|--------|-----------|
| Email adres | Geldig e-mailadres | Moet uniek zijn in de database |
| PIN | 6-cijferige PIN (verborgen invoer) | Exact 6 cijfers |
| Bevestig PIN | Herhaal de PIN | Moet overeenkomen |
| Rol | Keuze uit lijst | Zie rollen hieronder |

### Beschikbare rollen

| Keuze | Opgeslagen rol | Omschrijving |
|-------|---------------|--------------|
| `ADMIN` | `ROLE_ADMIN` | Volledige beheerderstoegang |
| `EDITOR` | `ROLE_EDITOR` | Kan content bewerken (standaard) |
| `READONLY` | `ROLE_READONLY` | Alleen lezen |

Elke gebruiker krijgt automatisch ook `ROLE_USER` toegewezen.

---

## Gebruiker bewerken

Er is geen apart console-commando voor het bewerken van gebruikers. Gebruik de Symfony console of directe database-queries.

### PIN wijzigen

```bash
php bin/console doctrine:query:dql \
  "SELECT u FROM App\Entity\User u WHERE u.email = 'gebruiker@voorbeeld.nl'"
```

Gebruik daarna een Symfony command of voer dit uit in een tijdelijk script:

```php
// Haal de gebruiker op
$user = $em->getRepository(User::class)->findOneBy(['email' => 'gebruiker@voorbeeld.nl']);

// Hash de nieuwe PIN
$user->setPassword($hasher->hashPassword($user, '123456'));

$em->flush();
```

### Rol wijzigen via SQL

```sql
UPDATE users
SET roles = '["ROLE_ADMIN"]'
WHERE email = 'gebruiker@voorbeeld.nl';
```

### Gebruiker deactiveren via SQL

```sql
UPDATE users
SET is_active = 0
WHERE email = 'gebruiker@voorbeeld.nl';
```

### Gebruiker opnieuw activeren via SQL

```sql
UPDATE users
SET is_active = 1, failed_login_attempts = 0, last_failed_at = NULL
WHERE email = 'gebruiker@voorbeeld.nl';
```

### Mislukte inlogpogingen resetten via SQL

```sql
UPDATE users
SET failed_login_attempts = 0, last_failed_at = NULL
WHERE email = 'gebruiker@voorbeeld.nl';
```

---

## Gebruikers overzicht opvragen

```bash
php bin/console doctrine:query:sql "SELECT id, email, roles, is_active, failed_login_attempts FROM users"
```

---

## Gebruiker verwijderen via SQL

```sql
DELETE FROM users WHERE email = 'gebruiker@voorbeeld.nl';
```
