# Rapport de performance — Ina Zaoui

> Objet : identification et correction des lenteurs de la page **« Invités »** (`/guests`),
> puis mesure de l'ensemble des pages du Front Office avant/après correction.

---

## 1. Contexte et jeu de données

Mesures réalisées sur la base **réelle** de l'application (MySQL / MAMP) :

| Élément | Volume |
|---|---|
| Utilisateurs (`user`) | 101 (dont **100 invités actifs** + 1 admin) |
| Médias (`media`) | 5 050 |

La page « Invités » liste les 100 invités actifs et affiche, pour chacun, son
nombre de photos.

---

## 2. Outils choisis

**Outil principal : le Web Profiler de Symfony.**
Il est déjà intégré au framework, sans coût ni configuration supplémentaire, et
expose exactement les indicateurs recherchés : **nombre de requêtes SQL**,
**temps d'exécution**, **mémoire**, et le détail de chaque requête Doctrine
(panneau *Doctrine*) qui permet de repérer immédiatement un problème de type N+1.

**Outil de mesure reproductible : `scripts/perf-measure.php`.**
Pour obtenir des chiffres stables et automatisables (et éviter la variabilité
d'une lecture manuelle dans la toolbar), un petit script amorce le kernel Symfony,
rejoue chaque page via le noyau HTTP et lit les collecteurs du profiler
(`db`, `memory`) ainsi qu'une mesure *wall-clock* médiane sur 5 itérations.

**Alternatives envisagées.** Des solutions APM comme **New Relic** ou **Blackfire**
sont plus complètes (profilage en production, graphes d'appels, suivi dans le
temps). Elles n'ont pas été retenues ici car le Web Profiler suffit à diagnostiquer
et prouver la correction d'un problème applicatif ciblé, sans dépendance externe.

### Indicateurs analysés pour chaque page
- **Nombre de requêtes SQL** : indicateur clé, révèle les problèmes N+1 ; il ne doit
  pas croître avec le volume de données.
- **Temps d'affichage (ms)** : temps de génération de la réponse côté serveur.
- **Mémoire (Mo)** : reflète la quantité de données hydratées.

---

## 3. Diagnostic de la lenteur de `/guests`

### Cause : problème **N+1** (requêtes en cascade)

Le contrôleur récupérait la liste des invités, puis le template affichait le
nombre de photos de chaque invité avec :

```twig
<h4>{{ guest.name }} ({{ guest.medias|length }})</h4>
```

`guest.medias` est une association **lazy** (`OneToMany`). Appeler `|length`
**initialise la collection**, ce qui déclenche **une requête SQL supplémentaire
par invité** — auxquelles s'ajoute le chargement des associations `EAGER` de
chaque média. Résultat : **1 requête (liste) + 100 requêtes (une par invité) = 101
requêtes** pour afficher une seule page.

Le panneau *Doctrine* du Web Profiler montrait bien cette centaine de `SELECT ...
FROM media WHERE user_id = ?` répétés.

### Mesure avant correction

| Page | Requêtes SQL | Temps (ms) | Mémoire (Mo) |
|---|---:|---:|---:|
| `/` (accueil) | 0 | 1.6 | 34.0 |
| `/about` | 0 | 1.3 | 34.0 |
| **`/guests`** | **101** | **176.7** | **34.0** |
| `/guest/{id}` | 2 | 4.1 | 34.0 |
| `/portfolio` | 3 | 3.9 | 34.0 |

`/guests` était ~**40× plus lente** que les autres pages du Front Office.

---

## 4. Correction appliquée

Le comptage des photos est désormais délégué à la base via **une seule requête
agrégée** (`GROUP BY`), au lieu d'hydrater les collections de médias en PHP.

**`src/Repository/UserRepository.php`**
```php
public function findActiveGuestsWithMediaCount(): array
{
    $rows = $this->createQueryBuilder('u')
        ->select('u AS guest', 'COUNT(m.id) AS mediaCount')
        ->leftJoin('u.medias', 'm')
        ->where('u.admin = false')
        ->andWhere('u.active = true')
        ->groupBy('u.id')
        ->orderBy('u.name', 'ASC')
        ->getQuery()
        ->getResult();

    return array_map(static fn (array $r) => [
        'guest' => $r['guest'],
        'mediaCount' => (int) $r['mediaCount'],
    ], $rows);
}
```

**`src/Controller/HomeController.php`** — appelle la nouvelle méthode.
**`templates/front/guests.html.twig`** — utilise le compteur pré-calculé :
```twig
<h4>{{ entry.guest.name }} ({{ entry.mediaCount }})</h4>
```

Aucune photo n'est plus chargée en mémoire pour cette page : le nombre est calculé
côté SGBD.

---

## 5. Mesure après correction

| Page | Requêtes SQL | Temps (ms) | Mémoire (Mo) |
|---|---:|---:|---:|
| `/` (accueil) | 0 | 1.7 | 28.0 |
| `/about` | 0 | 1.6 | 28.0 |
| **`/guests`** | **1** | **7.9** | **28.0** |
| `/guest/{id}` | 2 | 3.8 | 28.0 |
| `/portfolio` | 3 | 4.1 | 28.0 |

### Gains sur `/guests`

| Indicateur | Avant | Après | Gain |
|---|---:|---:|---:|
| Requêtes SQL | 101 | **1** | **−99 %** (÷101) |
| Temps serveur | 176.7 ms | **7.9 ms** | **≈ −96 %** (÷22) |
| Mémoire | 34.0 Mo | 28.0 Mo | −18 % |

> Remarque : les temps sont mesurés *in-process* (hors réseau/navigateur) et
> servent de comparaison relative avant/après. Le point déterminant est que le
> nombre de requêtes SQL est désormais **constant (1)** : la page ne ralentira
> plus quand le nombre d'invités augmentera.

---

## 6. Garde-fou : test de non-régression

Un test fonctionnel vérifie, via le profiler, que la page s'exécute en un nombre
**borné et constant** de requêtes (`tests/Controller/GuestsPagePerformanceTest.php`) :

```php
self::assertLessThanOrEqual(2, $queryCount);
```

Ainsi, toute future régression réintroduisant un N+1 sur cette page fera échouer
la suite de tests.

Un test d'intégration (`tests/Repository/UserRepositoryTest.php`) valide en plus
l'exactitude des compteurs (invités actifs uniquement, admin et invités bloqués
exclus, décompte correct).

---

## 7. Comment reproduire les mesures

```zsh
# Base de dev (MySQL) accessible via .env.local
php scripts/perf-measure.php /guests /guest/2 /portfolio /about /

# Suite de tests (dont la non-régression de performance)
composer test
```

---

## 8. Conclusion

La lenteur de la page « Invités » provenait d'un **problème N+1** classique
(`guest.medias|length` dans une boucle). En remplaçant l'hydratation des
collections par **une requête agrégée `GROUP BY`**, la page passe de **101 à 1
requête SQL** et d'environ **177 ms à 8 ms**, avec un coût désormais **indépendant
du volume d'invités**. Les autres pages du Front Office étaient déjà performantes
(0 à 3 requêtes) et restent inchangées. Un test de non-régression verrouille le
gain dans la durée.

