# Contribuer à Ina Zaoui

Merci de votre intérêt pour ce projet ! Ce guide décrit comment **signaler un
problème**, **proposer une fonctionnalité** et **contribuer** au code, aux tests
et à la documentation. Il vise à faciliter la passation entre développeurs et à
garder une base de code cohérente et de qualité.

---

## Sommaire

- [Code de conduite](#code-de-conduite)
- [Préparer son environnement](#préparer-son-environnement)
- [Signaler un problème (issue)](#signaler-un-problème-issue)
- [Proposer une fonctionnalité](#proposer-une-fonctionnalité)
- [Contribuer au code](#contribuer-au-code)
- [Contribuer aux tests](#contribuer-aux-tests)
- [Contribuer à la documentation](#contribuer-à-la-documentation)
- [Conventions de commit](#conventions-de-commit)
- [Processus de Pull Request](#processus-de-pull-request)

---

## Code de conduite

Restez courtois, constructif et respectueux dans les échanges (issues, revues,
commentaires). Les critiques portent sur le code, jamais sur les personnes.

---

## Préparer son environnement

Suivez la section **Installation** du [`README.md`](README.md) pour installer les
dépendances, configurer la base et charger les fixtures. Vérifiez que tout
fonctionne avant de commencer :

```zsh
composer install
php bin/console doctrine:fixtures:load --no-interaction
composer test
```

Si `composer test` passe (56+ tests au vert), votre environnement est prêt.

---

## Signaler un problème (issue)

Ouvrez une **issue GitHub** en décrivant clairement le bug. Un bon rapport
contient :

1. **Titre court et explicite** (ex. « La page /guests renvoie une 500 quand un album est vide »).
2. **Étapes de reproduction** — la liste précise des actions pour reproduire.
3. **Comportement attendu** vs **comportement observé**.
4. **Environnement** — version de PHP, OS, navigateur si pertinent.
5. **Preuves** — message d'erreur, capture d'écran, extrait de log (`var/log/`),
   ou trace du Web Profiler.

Avant de créer une issue, **recherchez** dans les issues existantes pour éviter
les doublons.

---

## Proposer une fonctionnalité

Ouvrez une issue avec le label **`enhancement`** et décrivez :

- le **besoin métier** ou le problème utilisateur que la fonctionnalité résout ;
- la **solution envisagée** (comportement attendu, impact sur le Front/Back Office) ;
- les **alternatives** éventuellement considérées.

Attendez un retour avant de développer une fonctionnalité importante : cela évite
d'investir du temps sur une piste qui ne serait pas retenue.

---

## Contribuer au code

### Workflow Git

1. Partez de `main` à jour :
   ```zsh
   git checkout main && git pull origin main
   ```
2. Créez une branche dédiée avec un préfixe explicite :
   - `feat/…` pour une fonctionnalité,
   - `fix/…` pour une correction,
   - `perf/…` pour une optimisation,
   - `docs/…` pour la documentation,
   - `test/…` pour les tests.
   ```zsh
   git checkout -b feat/ma-fonctionnalite
   ```
3. Développez par petits commits atomiques.
4. Ouvrez une Pull Request vers `main` (voir plus bas).

> **Une branche = une intention.** Évitez de mélanger plusieurs sujets non liés
> dans la même PR.

### Style de code

- Respectez les **standards Symfony** et **PSR-12**.
- Le code, les noms de variables/méthodes et les messages de commit sont en
  **anglais** ; les commentaires métier peuvent être en français.
- Utilisez l'**injection de dépendances** (pas de `getDoctrine()`, pas de chemins
  relatifs pour les fichiers — passez par les services comme `MediaUploader`).
- Gardez les contrôleurs **fins** : la logique de requête va dans les *repositories*,
  la logique métier dans des *services*.
- Validez le schéma après toute modification d'entité :
  ```zsh
  php bin/console doctrine:schema:validate
  ```
  et générez une migration si nécessaire (`make:migration`).

---

## Contribuer aux tests

**Toute contribution de code doit être accompagnée de tests.** La suite doit
rester verte et la couverture des lignes de `src/` **>= 70 %**.

- Placez les tests dans `tests/`, en **miroir** de l'arborescence de `src/` :
  - **unitaires** pour les entités, services, formulaires (rapides, sans I/O) ;
  - **fonctionnels** (`WebTestCase`) pour les contrôleurs et le contrôle d'accès ;
  - **intégration** pour les requêtes de repository.
- Réutilisez les **fixtures** (`src/DataFixtures/AppFixtures.php`) et leurs
  constantes plutôt que des valeurs en dur.
- Testez le **cas nominal ET les cas d'erreur** (404, 403, accès refusé, entrée invalide).
- Nommez les tests par leur **comportement** (ex. `testBlockedGuestCannotLogIn`).

Lancer les tests et la couverture :

```zsh
composer test
composer test:coverage   # rapport HTML dans var/coverage/index.html
```

> Les tests sont isolés (SQLite + `var/test_uploads`) : ils ne doivent **jamais**
> dépendre d'un serveur MySQL ni modifier `public/uploads`.

---

## Contribuer à la documentation

La documentation est aussi importante que le code :

- **`README.md`** — prérequis, installation, usage. À mettre à jour dès qu'une
  étape d'installation ou une commande change.
- **`CONTRIBUTING.md`** — ce guide.
- **`docs/`** — documents transverses (ex. rapport de performance).

Vérifiez l'orthographe, les liens et les blocs de code (langage indiqué,
commandes réellement testées).

---

## Conventions de commit

Ce projet suit **[Conventional Commits](https://www.conventionalcommits.org/)** :

```
<type>(<scope>): <résumé impératif court>

<corps optionnel : contexte, changements, résultat>
```

Types courants : `feat`, `fix`, `perf`, `test`, `docs`, `refactor`, `chore`.

Exemples réels du projet :

```
perf(guests): fix N+1 on the guests page (101 -> 1 SQL query)
test: add fixtures and PHPUnit suite (unit + functional)
```

Rédigez les messages en **anglais**, à l'impératif présent.

---

## Processus de Pull Request

1. Assurez-vous que **`composer test` passe** et que la couverture reste >= 70 %.
2. Mettez à jour la **documentation** impactée (`README`, `docs/`).
3. Ouvrez la PR vers **`main`** avec un titre au format Conventional Commits et
   une description claire : **contexte**, **changements**, **validation**
   (résultat des tests, mesures éventuelles).
4. Liez l'issue concernée (`Closes #NN`).
5. Répondez aux retours de revue ; gardez l'historique propre (rebase si besoin).
6. La branche est mergée par un mainteneur une fois la revue approuvée et la CI
   au vert.

Merci pour votre contribution ! 🎉
