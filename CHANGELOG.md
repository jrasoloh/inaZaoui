# Journal des modifications — Ina Zaoui

> Journal de travail tenu pour la présentation finale du projet.
> Il retrace, par jalon, le **contexte/problème**, **ce qui a été fait** et la **validation**.
> Chaque jalon correspond à une branche / Pull Request. Les hash de commit sont indiqués entre parenthèses.

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Le plus récent est en haut.

---

## En cours — `ci/github-actions`

Mise en place d'un pipeline d'intégration continue (CI) sur GitHub Actions.

### ✅ Pipeline CI (`.github/workflows/ci.yml`)
- Déclenché à chaque **push sur `main`** et à chaque **Pull Request**.
- **Job `tests`** : PHP 8.4 + extensions, cache Composer, `composer validate
  --strict --no-check-publish`, install, puis `composer test` (suite PHPUnit
  auto-contenue SQLite — aucune base externe requise).
- **Job `static-analysis`** : `composer phpstan` (analyse statique).

### ✅ Analyse statique (PHPStan)
- Ajout de `phpstan/phpstan` + extension `phpstan/phpstan-doctrine` (dev).
- `phpstan.dist.neon` : niveau 5 sur `src/`, `objectManagerLoader`
  (`tests/object-manager.php`) pour la résolution du mapping Doctrine,
  `allowNullablePropertyForRequiredField` (motif Symfony des champs requis).
- Script `composer phpstan`.

### ✅ Corrections révélées par PHPStan
- `MediaController::add()` : garde `instanceof User` avant `setUser()`
  (`getUser()` renvoie `?UserInterface`).
- `User::$medias` : annotation générique `Collection<int, Media>`.
- `UserRepository` : suppression du tag `@implements` erroné
  (`PasswordUpgraderInterface` n'est pas générique).

**Validation** (local, PHP 8.4) : `composer test` → **56 tests, 153 assertions, OK** ;
`composer phpstan` → **No errors** ; `composer validate --strict --no-check-publish` → OK.

---

## En cours — `docs/readme-contributing`

Documentation de passation pour le nouveau développeur.

### ✅ `README.md` réécrit (clair et concis)
- **Structure** : Prérequis / Installation / Usage (+ Tests, Structure du projet).
- **Prérequis** actualisés (PHP ≥ 8.4, Symfony 8.1, MySQL, Composer, pcov/Xdebug optionnel).
- **Installation** en étapes reproductibles : dépendances, `.env.local`, base +
  migrations, puis deux options de données — **fixtures** (recommandé, avec table
  des identifiants) ou dumps SQL historiques.
- **Usage** : lancement du serveur, table des URLs et des accès, description des
  fonctionnalités Front/Back Office.

### ✅ `CONTRIBUTING.md` ajouté
- Directives pour **signaler un problème**, **proposer une fonctionnalité**, et
  **contribuer** au code, aux tests et à la documentation.
- Workflow Git (branches préfixées, PR vers `main`), style de code (standards
  Symfony/PSR-12, injection de dépendances), conventions **Conventional Commits**.
- Exigences de tests (miroir de `src/`, réutilisation des fixtures, couverture ≥ 70 %)
  et checklist de Pull Request.

---

## En cours — `feat/performance-guests`

Optimisation de la page **« Invités »** (`/guests`) et rapport de performance.

### ✅ Correction du N+1 sur `/guests`
- **Problème** : le template appelait `guest.medias|length` dans une boucle sur
  les 100 invités → **1 + 100 = 101 requêtes SQL** (N+1), page ~40× plus lente
  que les autres pages du Front Office (mesuré sur la base réelle : 101 users, 5050 médias).
- **Fait** : `UserRepository::findActiveGuestsWithMediaCount()` calcule le nombre
  de photos en **une seule requête agrégée** (`GROUP BY`). `HomeController::guests()`
  et `templates/front/guests.html.twig` adaptés (compteur pré-calculé).
- **Résultat** : `/guests` passe de **101 → 1 requête** et de **~177 ms → ~8 ms**
  (÷22), coût désormais **indépendant du nombre d'invités**.

### ✅ Outillage & garde-fous
- `scripts/perf-measure.php` : sonde reproductible (requêtes SQL / temps / mémoire
  via le Web Profiler) pour les pages du Front Office.
- `tests/Controller/GuestsPagePerformanceTest` : non-régression (≤ 2 requêtes).
- `tests/Repository/UserRepositoryTest` : exactitude des compteurs (invités actifs
  uniquement, admin/bloqués exclus).
- `docs/RAPPORT_PERFORMANCE.md` : rapport complet avant/après.

**Validation** : `composer test` → **56 tests, 153 assertions, OK**.

---

## En cours — `feat/tests`

Mise en place des tests automatisés (fixtures + PHPUnit) avec une couverture ≥ 70 %.

### ✅ Fixtures représentatives
- **Ajouté** : `doctrine/doctrine-fixtures-bundle` (dev/test) et `src/DataFixtures/AppFixtures.php`.
- **Scénarios couverts** : photographe/admin, deux invités actifs, un invité bloqué (révoqué),
  albums (`Nature`, `Ville`), médias appartenant à l'admin, à un invité actif, à un invité bloqué,
  ainsi qu'un média sans propriétaire. Les identifiants sont exposés en constantes réutilisées par les tests.

### ✅ Suite de tests PHPUnit
- **Environnement isolé** : base **SQLite** dédiée (`var/test.db`) recréée + rechargée avant chaque
  test via `tests/FixturesTrait`. `app.uploads_dir` est surchargé vers `var/test_uploads` en env de test
  pour ne **jamais** toucher `public/uploads`.
- **Tests unitaires** : `User` (rôles, identifiant, collection médias), `Media`, `Album`,
  `MediaUploader` (upload/suppression), `UserChecker` (refus des comptes révoqués), `GuestType`.
- **Tests fonctionnels** : Front Office (`HomeController` — accueil, invités, portfolio, 404),
  sécurité (login/logout, mauvais mot de passe, invité bloqué refusé), contrôle d'accès `^/admin`,
  gestion des invités (ajout, blocage/déblocage, révocation CSRF, suppression), albums, médias.
- **Test d'intégration** : `MediaRepository::findVisibleByAlbum` masque bien les photos des invités bloqués.

**Corrections de configuration nécessaires aux tests**
- `framework.yaml` : suppression de l'option obsolète `annotations` (bloquait le boot du kernel en env test).
- `.env.test` : `DATABASE_URL` SQLite. `services.yaml` : override `app.uploads_dir` en `when@test`.

**Validation**
- `composer test` : **54 tests, 144 assertions, OK**.
- `composer test:coverage` : lignes **95,57 %** (302/316), méthodes **93 %** — objectif ≥ 70 % atteint.

---

## En cours — `feat/guest-management`

Implémentation de la gestion des invités (brief : interface admin + contrôle d'accès).

### ✅ Gestion des invités — admin + front

**Modèle**
- `User.active` (bool, défaut `true`) + migration `Version20260804120812`.
- Cascade `orphanRemoval` sur `User.medias` : supprimer un invité supprime ses médias en DB.

**Contrôle d'accès**
- `src/Security/UserChecker.php` : refuse la connexion aux invités dont `active = false` (`CustomUserMessageAccountStatusException`).
- `security.yaml` : `user_checker: App\Security\UserChecker` branché sur le firewall `main`.
- `GuestController` annoté `#[IsGranted('ROLE_ADMIN')]` : inaccessible aux invités (ROLE_USER → 403).

**Interface admin** (`/admin/guest`, ROLE_ADMIN uniquement)
- **Liste** : tableau des invités avec badge Actif/Bloqué, bouton Bloquer/Débloquer individuel, cases à cocher pour la révocation en masse, bouton Supprimer.
- **Ajout** : formulaire `GuestType` (nom, email, description, mot de passe) ; invité créé avec `active=true`, mot de passe haché.
- **Bloquer/Débloquer** : `GET /admin/guest/toggle/{id}` — bascule `active`.
- **Révoquer une sélection** : `POST /admin/guest/revoke` (CSRF protégé) — met `active=false` sur les ids cochés.
- **Supprimer** : `GET /admin/guest/delete/{id}` — supprime les fichiers physiques via `MediaUploader` puis la ligne `user` (cascade ORM supprime les médias en DB).
- Messages flash (succès/erreur) affichés dans `admin.html.twig`.
- Lien « Invités » dans la sidebar admin (était vide, maintenant câblé sur `admin_guest_index`).

**Front**
- `/guests` : n'affiche que les invités `active=true`.
- `/guest/{id}` : 404 si invité bloqué ou inexistant.
- Portfolio par album : `MediaRepository::findVisibleByAlbum()` exclut les photos des invités bloqués.

**Validation** (tests automatisés)
- `scripts/test-guests.sh` : ajout → login OK → blocage → login refusé → invisible front → suppression.
- Test cascade : invité avec média + fichier → suppression → média et fichier effacés.
- Test ROLE : invité (ROLE_USER) → `/admin/guest` = 403, `/admin/media` = 200.

Résolution des anomalies de l'application (brief : vérification des fichiers uploadés + gestion dynamique des connexions).

### ✅ Anomalie 1 — Validation des fichiers uploadés (`fce2453`)
- **Problème** : `MediaController::add()` déplaçait et persistait **n'importe quel** fichier uploadé, sans aucune vérification (type, taille, présence).
- **Fait** :
  - Contraintes sur `Media::$file` : `#[Assert\NotNull]` (fichier requis) + `#[Assert\Image]` (vraie image, MIME `jpeg/png/webp/gif`, taille max 8 Mo).
  - Ajout de `{{ form_errors(form) }}` dans le template d'ajout média pour afficher les erreurs de niveau formulaire (au lieu d'un échec silencieux).
  - Script de test `scripts/test-upload.sh` (non-image → rejeté 200 + message ; image valide → acceptée 302).
- **Validation** : test automatisé OK + upload réel vérifié en navigateur.

### ✅ Durcissement du stockage des uploads (`src/Service/MediaUploader.php`, `services.yaml`, `MediaController`)
- **Problème** : `MediaController` utilisait un chemin **relatif** (`move('uploads/', …)` et `unlink($media->getPath())`) qui ne fonctionne que si le répertoire courant du process est `public/`. Fragile (autre SAPI, commande console, worker → écriture/suppression au mauvais endroit).
- **Fait** :
  - Nouveau service `MediaUploader` : `upload(UploadedFile): string` (déplace vers un dossier **absolu configuré**, renvoie le chemin public relatif `uploads/<nom>`) et `remove(?string)` (suppression par `basename` dans ce dossier, sûre si le fichier manque).
  - Paramètre `app.uploads_dir = %kernel.project_dir%/public/uploads` + injection explicite dans `services.yaml`.
  - `MediaController` : `add()`/`delete()` délèguent au service ; plus aucun chemin relatif.
- **Validation** : cycle de vie complet automatisé (`scripts/test-upload.sh`) — non-image rejeté, image valide stockée sur le disque (dossier absolu), suppression admin → fichier physiquement retiré + ligne DB supprimée.

### ✅ Bug pagination admin des médias (`templates/admin/media/index.html.twig`, `MediaController`)
- **Problème** : le contrôleur paginait par **25**/page alors que le template calculait le nombre de pages avec **50** → la seconde moitié des médias (dont les plus récents) était **injoignable** via les liens (dernière page cliquable ≈ `ceil(total/50)` au lieu de `ceil(total/25)`).
- **Fait** :
  - Constante unique `MediaController::PER_PAGE = 25` utilisée pour le `limit`, l'`offset` **et** transmise au template (`perPage`) → plus de dérive possible.
  - Template : `totalPages = (total / perPage)|round(ceil)`.
  - Corrections annexes : `count($criteria)` au lieu de `count([])` (total juste aussi pour les non-admins) ; garde-fou `max(1, page)`.
- **Validation** : dernière page exposée = `ceil(total/25)` et le média d'id le plus élevé est bien atteignable sur cette dernière page (vérifié via le serveur).

### ✅ Anomalie 2 — User provider dynamique (BDD)
- **Problème** : authentification **in-memory** (`ina` en dur dans `security.yaml`) alors qu'une table `user` existe. L'entité `User` n'implémentait pas `UserInterface` et n'avait ni `password` ni rôles.
- **Fait** :
  - `User` implémente `UserInterface` + `PasswordAuthenticatedUserInterface` : `getUserIdentifier()` = **email**, `getRoles()` **dérivé de `admin`** (`ROLE_USER` + `ROLE_ADMIN` si admin), ajout de la propriété/colonne `password`.
  - Migration `Version20260803180840` : ajout colonne `password` + définition du mot de passe admin (`ina@zaoui.com`, hash bcrypt de `password`) — reproductible (testée rollback + forward).
  - `security.yaml` : provider **in-memory → entity** (`class: App\Entity\User`, `property: email`).
  - Formulaire de login : identifiant = **email** (label + `type="email"`).
- **Connexion** : désormais **`ina@zaoui.com` / `password`** (au lieu de `ina`).
- **Validation** : bon mot de passe → accès admin 200 ; mauvais mot de passe → rejeté (302 /login) ; non connecté → `/admin` refusé. Schéma en sync, aucune dépréciation.

### 🛠️ Environnement / outillage (hors dépôt)
- **phpMyAdmin** : bascule de la 4.9.10 (obsolète, spam de dépréciations PHP 8) vers la **5.2.0** déjà fournie par MAMP. Lien du menu WebStart de MAMP redirigé vers `/phpMyAdmin5/` (backup `index.php.bak-pma5`). URL d'admin BDD : `http://localhost:8888/phpMyAdmin5/`.
- **PHP 8.4** : installé via Homebrew (`php@8.4` 8.4.16), CLI basculé dessus (`~/.zshrc`, backup `~/.zshrc.bak-php84`).
- **`.php-version`** (`5df3b66`) : fige PHP **8.4.16** pour ce projet — corrige un `platform_check` fatal (worker php-fpm 8.3 périmé encore actif après la bascule).

---

## Symfony 8.1 — `feat/upgrade-symfony-8.1` (PR #3, `0956ae2`)

Montée de version majeure 7.4 → 8.1.

- **Prérequis** : Symfony 8 exige **PHP ≥ 8.4.1** (dry-run `composer update` bloquant en 8.3) → passage à PHP 8.4.
- **Dépendances** (`4749a2c`) :
  - `symfony/*` `7.4.*` → `8.1.*` (installé **8.1.2**), `php` `>=8.4`
  - `doctrine/dbal` `^3` → `^4.3`, `doctrine/doctrine-bundle` `^2` → `^3`, `symfony/monolog-bundle` `^3` → `^4`
- **Config migrée** (DBAL 4 / doctrine-bundle 3) :
  - `doctrine.yaml` : retrait de `use_savepoints`, `auto_generate_proxy_classes`, `proxy_dir`, `controller_resolver.auto_mapping`
  - `web_profiler.yaml` : retrait de `collect_serializer_data`
- **Nettoyage dépréciation DBAL** (`6d79c13`) : `serverVersion` fixé à `5.7.39` dans `.env`.
- **Validation** : aucune dépréciation first-party, schéma en sync, toutes les routes OK.

---

## Symfony 7.4 + login — `feat/upgrade-symfony-7.4` (PR #2, `836e4da`)

Montée de version 5.4 → 6.4 → 7.4 et correction de l'authentification.

- **Contrôleurs** :
  - Annotations `@Route` → attributs PHP 8 (`Routing\Attribute\Route`).
  - `getDoctrine()` (supprimé en SF6+) → injection de `ManagerRegistry`.
  - Suppression d'imports morts.
- **Config** :
  - `routes.yaml` : `type: annotation` → `attribute` ; suppression du `routes/annotations.yaml` redondant.
  - `framework.yaml` : `handle_all_throwables: true`.
  - Routing dev (`profiler`/`framework`) : ressources XML → PHP.
- **Dépendances** : `symfony/*` `6.4.*` → `7.4.*` (installé 7.4.15).
- **Fix login** (`4330d65`) : la recette Flex avait activé la CSRF *stateless* (incompatible sans pipeline JS) → retour à la CSRF **stateful** (`form_login.enable_csrf: true` + champ `_csrf_token` dans le template). Login vérifié (302 + accès admin 200).

---

## Onboarding / prise en main — `feature/setup-onboarding` (PR #1, `43c2429`)

Mise en route du projet récupéré et documentation.

- **Base de données** : import des dumps historiques (`album`, `user`, `media`) convertis PostgreSQL → MySQL ; migration Doctrine initiale ; schéma validé.
- **Médias volumineux (5000+)** : `public/uploads/*` ignoré par Git (`.gitignore`), dossier conservé via `public/uploads/.gitignore` ; script `scripts/setup-media.sh` pour synchroniser les images hors dépôt.
- **`README.md`** : guide de prise en main complet (prérequis, `.env.local`, init BDD, gestion des médias, URLs, identifiants).
- **`.gitignore`** : `.env.local` / `.env.*.local` non suivis (credentials hors dépôt).


