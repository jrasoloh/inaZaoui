# Guide de déploiement — Ina Zaoui

Ce document décrit comment déployer l'application en production. Le projet est
livré avec une **image Docker** de production (`Dockerfile`) et une stack
`docker-compose.yml` permettant de **tester le déploiement en local** avant de
viser un hébergeur réel.

> Résumé de l'architecture : un conteneur **PHP 8.4 + Apache** sert le site
> (racine `public/`), connecté à une base **MySQL**. Les photos uploadées sont
> stockées sur un **volume persistant**.

---

## Sommaire

- [Prérequis](#prérequis)
- [Variables d'environnement](#variables-denvironnement)
- [Déploiement local avec Docker (recommandé pour tester)](#déploiement-local-avec-docker-recommandé-pour-tester)
- [Initialisation de la base de données](#initialisation-de-la-base-de-données)
- [Persistance des uploads](#persistance-des-uploads)
- [Passage en production réelle](#passage-en-production-réelle)
- [Checklist de mise en production](#checklist-de-mise-en-production)
- [Dépannage (pièges rencontrés)](#dépannage-pièges-rencontrés)

---

## Prérequis

- **Docker** et **Docker Compose** (pour le déploiement conteneurisé).
- Ou, pour un hébergeur classique : **PHP 8.4**, **Composer**, un serveur web
  (Apache/Nginx) et une base **MySQL 8**.

---

## Variables d'environnement

Ces variables sont fournies par l'hébergeur (jamais commitées) :

| Variable | Rôle | Exemple |
|----------|------|---------|
| `APP_ENV` | Environnement Symfony | `prod` |
| `APP_DEBUG` | Mode debug (0 en prod) | `0` |
| `APP_SECRET` | Clé secrète (CSRF, etc.) | chaîne aléatoire de 32+ caractères |
| `DATABASE_URL` | Connexion à la base | `mysql://user:pass@host:3306/inazaoui?serverVersion=8.0&charset=utf8mb4` |
| `MAILER_DSN` | Transport e-mail | `null://null` si non utilisé |
| `MESSENGER_TRANSPORT_DSN` | Transport Messenger | `doctrine://default?auto_setup=0` |

> ⚠️ **Ne jamais committer de secret.** En production réelle, générez un vrai
> `APP_SECRET` et une `DATABASE_URL` propres, fournis par l'hébergeur.

---

## Déploiement local avec Docker (recommandé pour tester)

Depuis la racine du projet :

```zsh
# 1) Construire l'image et démarrer l'app + la base
docker compose up --build -d

# 2) Créer le schéma de base de données (voir section dédiée ci-dessous)
docker compose exec app php bin/console doctrine:schema:create
docker compose exec app php bin/console doctrine:migrations:version --add --all --no-interaction

# 3) Ouvrir le site
open http://localhost:8080
```

Arrêter la stack :

```zsh
docker compose down          # garde les volumes (données conservées)
docker compose down -v       # supprime aussi les volumes (remise à zéro)
```

**Ce que fait l'image** (`Dockerfile`) :
- installe les extensions PHP nécessaires (`intl`, `pdo_mysql`, `zip`, `opcache`) ;
- active OPcache (perf de production) ;
- installe les dépendances **sans dev** (`composer install --no-dev --optimize-autoloader`) ;
- configure Apache pour Symfony (racine `public/`, réécriture vers `index.php`) ;
- prépare le cache de prod au démarrage (`cache:warmup`) via `docker/entrypoint.sh`.

---

## Initialisation de la base de données

⚠️ **Point important.** Historiquement, le schéma provenait de dumps SQL, et les
migrations du projet sont **incrémentales** (`ALTER TABLE` pour ajouter les
colonnes `password` et `active`). Elles **ne créent donc pas** les tables de
zéro : lancer `doctrine:migrations:migrate` sur une base vide échoue
(« Table 'user' doesn't exist »).

Pour une **base neuve**, on crée le schéma depuis les entités Doctrine, puis on
marque les migrations comme appliquées :

```zsh
php bin/console doctrine:schema:create
php bin/console doctrine:migrations:version --add --all --no-interaction
```

Ainsi le schéma est complet et l'historique des migrations est cohérent (les
futures migrations s'appliqueront normalement).

> 💡 **Amélioration recommandée** (hors périmètre de cette étape) : générer une
> **migration initiale** consolidée décrivant tout le schéma courant, pour que
> `doctrine:migrations:migrate` fonctionne « from scratch ».

---

## Persistance des uploads

Les photos uploadées via l'admin sont écrites dans `public/uploads/`. Sur la
plupart des hébergeurs, le système de fichiers du conteneur est **éphémère** :
il est **remis à zéro à chaque redéploiement**. Sans persistance, **les photos
seraient perdues**.

La stack Docker règle ce point avec un **volume nommé** monté sur ce dossier :

```yaml
volumes:
  - uploads_data:/var/www/html/public/uploads
```

En production réelle, utilisez selon l'hébergeur : un **volume/disque
persistant**, ou mieux, un **stockage objet** (type S3) via un adaptateur de
filesystem.

---

## Passage en production réelle

Quelle que soit la cible (PaaS, VPS, cloud), les étapes de mise en production
sont :

1. **Construire l'image** (ou faire un `composer install --no-dev
   --optimize-autoloader` sur le serveur).
2. **Fournir les variables d'environnement** (voir tableau) via l'hébergeur.
3. **Préparer le cache de prod** : `php bin/console cache:clear` puis
   `cache:warmup`.
4. **Initialiser / mettre à jour la base** (voir section dédiée).
5. **Servir le dossier `public/`** en HTTPS, avec réécriture vers `index.php`.

### Autres cibles d'hébergement

- **PaaS git-push** (Heroku, Scalingo, Platform.sh) : ajouter un `Procfile`
  (`web: heroku-php-apache2 public/`) et configurer les variables d'env dans le
  tableau de bord. Le stockage des uploads doit pointer vers un service externe
  (disque persistant / S3).
- **VPS classique** (Nginx + PHP-FPM) : `root` sur `public/`, bloc `try_files
  $uri /index.php$is_args$args;`, PHP-FPM en 8.4, et un déploiement par
  `git pull` + `composer install --no-dev` + `cache:warmup` + migrations.

---

## Checklist de mise en production

- [ ] `APP_ENV=prod` et `APP_DEBUG=0`
- [ ] `APP_SECRET` unique et secret (pas la valeur du dépôt)
- [ ] `DATABASE_URL` de production (utilisateur dédié, pas `root`)
- [ ] Dépendances installées **sans dev** (`--no-dev`)
- [ ] Cache de prod généré (`cache:warmup`)
- [ ] Schéma de base initialisé / migrations à jour
- [ ] Dossier `public/uploads/` sur un **stockage persistant**
- [ ] **HTTPS** activé (et `trusted_proxies` configuré si derrière un reverse proxy)
- [ ] Sauvegardes de la base et des uploads planifiées

---

## Dépannage (pièges rencontrés)

Deux problèmes réels ont été rencontrés (et corrigés) en testant ce déploiement.
Ils sont documentés ici car ils sont instructifs :

### 1. `Class "…\DebugBundle" not found` (erreur 500)

**Cause** : avec **Apache + mod_php**, les variables d'environnement de l'OS ne
sont **pas** exposées automatiquement dans `$_SERVER`. Symfony ne voyait donc
pas `APP_ENV=prod`, retombait sur le `.env` (`APP_ENV=dev`) et tentait de charger
le `DebugBundle`, absent en prod (`--no-dev`).

**Correction** : transmettre explicitement les variables à PHP via `PassEnv`
dans la config Apache (`docker/apache/000-default.conf`) :

```apache
PassEnv APP_ENV
PassEnv APP_SECRET
PassEnv DATABASE_URL
# ...
```

### 2. `Table 'user' doesn't exist` au lancement des migrations

**Cause** : les migrations sont incrémentales et supposent des tables
existantes. Voir [Initialisation de la base de données](#initialisation-de-la-base-de-données).

**Correction** : initialiser le schéma avec `doctrine:schema:create`, puis
marquer les migrations comme appliquées.

