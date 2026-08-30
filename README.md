# Ina Zaoui — Portfolio photo

Application web de portfolio pour la photographe **Ina Zaoui**, développée avec
**Symfony 8.1**. Le site expose un **Front Office** public (accueil, portfolio par
album, présentation des invités) et un **Back Office** d'administration
(gestion des albums, des médias et des invités avec contrôle d'accès).

> 📓 L'historique détaillé des évolutions est dans [`CHANGELOG.md`](CHANGELOG.md).
> Le rapport de performance est dans [`docs/RAPPORT_PERFORMANCE.md`](docs/RAPPORT_PERFORMANCE.md).
> Pour contribuer, voir [`CONTRIBUTING.md`](CONTRIBUTING.md).

---

## Sommaire

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Usage](#usage)
- [Tests](#tests)
- [Structure du projet](#structure-du-projet)

---

## Prérequis

| Outil | Version | Notes |
|-------|---------|-------|
| **PHP** | **>= 8.4** | requis par Symfony 8.1 (extensions `ctype`, `iconv`) |
| **Composer** | 2.x | gestionnaire de dépendances PHP |
| **MySQL** | 5.7+ / 8.x | base de données (ex. MAMP sur `127.0.0.1:8889`) |
| **Symfony CLI** | *(optionnel)* | serveur de dev pratique (`symfony serve`) |
| **pcov** ou **Xdebug** | *(optionnel)* | uniquement pour la couverture de tests |

> SQLite est utilisé **automatiquement** pour la suite de tests : aucun serveur
> MySQL n'est nécessaire pour lancer les tests.

---

## Installation

### 1. Cloner et installer les dépendances

```zsh
git clone git@github.com:jrasoloh/inaZaoui.git
cd inaZaoui
composer install
```

### 2. Configurer l'environnement

Créer un fichier local **non versionné** `.env.local` à la racine :

```dotenv
DATABASE_URL="mysql://root:root@127.0.0.1:8889/inazaoui?serverVersion=5.7&charset=utf8mb4"
```

> Adaptez identifiant, mot de passe, hôte et port à votre machine.
> Ne committez jamais `.env.local` (déjà ignoré par Git).

### 3. Créer la base et le schéma

```zsh
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

### 4. Charger un jeu de données

**Option A — Fixtures (recommandé pour le développement)**

Charge un jeu de données représentatif (admin, invités actifs, invité bloqué,
albums et médias de démonstration) :

```zsh
php bin/console doctrine:fixtures:load --no-interaction
```

Identifiants créés par les fixtures :

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Administrateur | `admin@example.com` | `adminpass` |
| Invité actif | `alice@example.com` | `alicepass` |
| Invité bloqué | `bob@example.com` | `bobpass` |

**Option B — Données historiques (dumps SQL)**

Les dumps historiques (`album.sql`, `user.sql`, `media.sql`) sont au format
PostgreSQL. Pour les importer en MySQL, il faut convertir les préfixes
(`public.` / `"user"`) puis importer dans l'ordre **album → user → media**.
La procédure complète est documentée dans le [`CHANGELOG.md`](CHANGELOG.md)
(jalon « Onboarding »).

### 5. Synchroniser les images (optionnel)

Le dossier `public/uploads/` (5000+ fichiers) **n'est pas versionné**. Pour y
copier des médias existants :

```zsh
scripts/setup-media.sh /chemin/vers/backup.zip
# ou
scripts/setup-media.sh /chemin/vers/un/dossier/uploads
```

---

## Usage

### Lancer le serveur

```zsh
symfony serve
# ou, sans la Symfony CLI :
php -S 127.0.0.1:8000 -t public/
```

### URLs principales

| Zone | URL | Accès |
|------|-----|-------|
| Accueil | `http://127.0.0.1:8000/` | public |
| Portfolio | `http://127.0.0.1:8000/portfolio` | public |
| Invités | `http://127.0.0.1:8000/guests` | public |
| Connexion | `http://127.0.0.1:8000/login` | public |
| Administration | `http://127.0.0.1:8000/admin/` | `ROLE_ADMIN` |

### Fonctionnalités

**Front Office** — accueil, portfolio filtrable par album, liste des invités
(seuls les invités actifs sont affichés ; les photos des invités bloqués sont
masquées du portfolio), page « À propos ».

**Back Office** (`ROLE_ADMIN`) :
- **Albums** — créer, modifier, supprimer (`/admin/album`).
- **Médias** — lister (paginé), ajouter (upload validé : image, <= 8 Mo),
  supprimer (`/admin/media`).
- **Invités** — ajouter, bloquer/débloquer, révoquer en masse (CSRF),
  supprimer (`/admin/guest`). Un invité bloqué ne peut plus se connecter et
  disparaît du Front Office.

---

## Tests

La suite de tests est **auto-contenue** : elle utilise une base **SQLite**
dédiée (`var/test.db`), recréée et rechargée avec les fixtures avant chaque
test. Aucun serveur MySQL n'est requis, et `public/uploads` n'est jamais
modifié (les uploads de test vont dans `var/test_uploads`).

```zsh
composer test           # lance toute la suite (PHPUnit)
composer test:coverage  # rapport de couverture -> var/coverage/index.html
```

La couverture de lignes du dossier `src/` est **> 90 %** (objectif >= 70 %).

## Analyse statique (PHPStan)

**PHPStan** analyse le code sans l'exécuter et repère les erreurs potentielles
(méthodes inexistantes, types incohérents...). L'extension `phpstan-doctrine`
lui apprend à comprendre les entités et repositories Doctrine.

```zsh
composer phpstan        # analyse le dossier src/ (niveau 5)
```

Configuration : `phpstan.dist.neon`.

## Intégration continue (CI)

À chaque `push` sur `main` et à chaque Pull Request, **GitHub Actions** exécute
automatiquement (`.github/workflows/ci.yml`) :

1. l'installation de PHP 8.4 et des dépendances Composer ;
2. la **suite de tests** (`composer test`) ;
3. l'**analyse statique** (`composer phpstan`).

Une PR ne peut être mergée sereinement que si ces vérifications sont **au vert**.

---

## Structure du projet

```
src/
  Controller/        Contrôleurs Front (HomeController) et Admin/
  Entity/            Entités Doctrine (User, Album, Media)
  Form/              Types de formulaires (GuestType, ...)
  Repository/        Requêtes Doctrine (findVisibleByAlbum, ...)
  Security/          UserChecker (refus des invités bloqués)
  Service/           MediaUploader (stockage des fichiers)
  DataFixtures/      Jeu de données de démo/test
templates/           Vues Twig (front/ et admin/)
tests/               Tests unitaires, fonctionnels et d'intégration
docs/                Rapport de performance
scripts/             Scripts utilitaires (setup-media, perf-measure, ...)
migrations/          Migrations Doctrine
```
