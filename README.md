# Ina Zaoui

Projet Symfony 5.4 (portfolio photo).

## Objectif de ce README

Ce guide permet a n'importe quel developpeur de:
- lancer le projet en local rapidement,
- initialiser la base avec les donnees historiques,
- charger les medias sans les versionner dans Git.

## Prerequis

- PHP >= 8.0
- Composer
- MySQL (exemple valide: MAMP sur `127.0.0.1:8889`)
- Optionnel: Symfony CLI

## 1) Installation projet

```zsh
cd /path/to/inaZaoui
composer install
```

## 2) Configuration environnement

Creer un fichier local non versionne `/.env.local`:

```dotenv
DATABASE_URL="mysql://root:root@127.0.0.1:8889/inazaoui?serverVersion=5.7&charset=utf8mb4"
```

Remarques:
- adapter `root:root`, host et port selon votre machine,
- ne pas committer `/.env.local`.

## 3) Initialisation base de donnees

Creer la base puis le schema depuis les entites Doctrine:

```zsh
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:create
php bin/console doctrine:schema:validate
```

## 4) Import des donnees historiques SQL

Les dumps fournis (`album.sql`, `user.sql`, `media.sql`) sont au format PostgreSQL (`public.` / `"user"`).
Si vous etes en MySQL, convertir puis importer.

### Conversion PostgreSQL -> MySQL (one-shot)

```zsh
python3 << 'EOF'
from pathlib import Path

rules = {
	'/path/to/backup/album.sql': ('INSERT INTO public.album', 'INSERT INTO album', '/tmp/album-mysql.sql'),
	'/path/to/backup/user.sql': ('INSERT INTO public."user"', 'INSERT INTO user', '/tmp/user-mysql.sql'),
	'/path/to/backup/media.sql': ('INSERT INTO public.media', 'INSERT INTO media', '/tmp/media-mysql.sql'),
}

for src, (old, new, dst) in rules.items():
	content = Path(src).read_text(encoding='utf-8')
	Path(dst).write_text(content.replace(old, new), encoding='utf-8')

print('SQL conversion complete.')
EOF
```

### Import MySQL (ordre obligatoire)

```zsh
mysql -h 127.0.0.1 -P 8889 -u root -proot inazaoui < /tmp/album-mysql.sql
mysql -h 127.0.0.1 -P 8889 -u root -proot inazaoui < /tmp/user-mysql.sql
mysql -h 127.0.0.1 -P 8889 -u root -proot inazaoui < /tmp/media-mysql.sql
```

Puis verifier:

```zsh
php bin/console doctrine:schema:validate
```

## 5) Gestion des images (5000+ fichiers)

`public/uploads` contient beaucoup de fichiers et ne doit pas etre pousse sur Git.

Comportement actuel:
- `public/uploads/*` est ignore par `/.gitignore`,
- `public/uploads/.gitignore` est versionne pour conserver le dossier.

Script de sync media:

```zsh
scripts/setup-media.sh /chemin/vers/backup.zip
scripts/setup-media.sh /chemin/vers/un/dossier/uploads
```

Le script copie les medias vers `public/uploads` et affiche le total de fichiers detectes.

## 6) Lancer l'application

```zsh
symfony serve
```

ou sans Symfony CLI:

```zsh
php -S 127.0.0.1:8000 -t public/
```

URLs utiles:
- Front: `http://127.0.0.1:8000/`
- Login: `http://127.0.0.1:8000/login`
- Admin: `http://127.0.0.1:8000/admin/`

Identifiants admin:
- email: `ina@zaoui.com`
- password: `password`

## 7) Tests automatises

La suite de tests (PHPUnit) est **auto-contenue**: elle utilise une base **SQLite**
dediee (`var/test.db`), recreee et rechargee avec les fixtures avant chaque test.
Aucun serveur MySQL ni media reel n'est requis, et `public/uploads` n'est jamais
modifie (les uploads de test vont dans `var/test_uploads`).

Contenu:
- **Fixtures** (`src/DataFixtures/AppFixtures.php`): jeu de donnees representatif
  (photographe/admin, invites actifs, invite bloque, albums, medias).
- **Tests unitaires**: entites (`User`, `Media`, `Album`), service `MediaUploader`,
  `UserChecker`, formulaire `GuestType`.
- **Tests fonctionnels/integration**: Front Office (`HomeController`), securite
  (login/logout, refus des invites bloques), controle d'acces admin, gestion des
  invites, albums, medias, et `MediaRepository::findVisibleByAlbum`.

Lancer les tests:

```zsh
composer test
# ou
php bin/phpunit
```

Couverture de code (necessite l'extension `pcov` ou `xdebug`):

```zsh
composer test:coverage
# rapport HTML: var/coverage/index.html
```

La couverture des lignes du dossier `src/` est superieure a 70% (objectif du brief).

## 8) Roadmap technique

Etat actuel: import SQL historique + medias externes.

Prochaine etape recommandee:
1. creer une migration initiale Doctrine,
2. introduire des fixtures (`DoctrineFixturesBundle`),
3. retirer la dependance au dump SQL pour un bootstrap 100% reproductible.
