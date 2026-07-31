# AbstractRepository - Référence Technique

## Description

Classe abstraite fournissant les opérations CRUD de base pour les repositories Laravel avec une interface type-safe basée sur des enregistrements immuables (`AbstractRecord`). Elle sert de fondation pour toutes les interactions avec la base de données.

## Hiérarchie / Implémentations

```
AbstractRepositoryInterface<TModel, TRecord>
    └── AbstractRepository<TModel, TRecord> (abstract)
            └── [Vos repositories concrets]
```

**Interfaces implémentées :**
- `AbstractRepositoryInterface<TModel, TRecord>`

**Classes parentes :** Aucune

## Rôle principal

Cette classe abstraite agit comme une couche d'abstraction entre les modèles Eloquent et la logique métier. Elle permet de :

1. **Centraliser** les opérations CRUD (Create, Read, Update, Delete)
2. **Typer** les opérations avec des enregistrements immuables (`AbstractRecord`)
3. **Standardiser** les interactions avec la base de données
4. **Simplifier** la gestion des soft deletes
5. **Filtrer** les requêtes avec des filtres cluster sur colonnes JSON

## API / Méthodes publiques

### `__construct(string $modelClass, string $recordClass)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `class-string<TModel>` | Nom de la classe du modèle Eloquent |
| `$recordClass` | `class-string<TRecord>` | Nom de la classe Record utilisée pour le transfert de données |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(
            User::class,
            UserRecord::class
        );
    }
}
```

---

### `whereCluster(string $column, string $query): self`

Applique un filtre cluster sur une colonne JSON. Permet des requêtes complexes sur les données JSON.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$column` | `string` | Nom de la colonne JSON |
| `$query` | `string` | Expression de requête cluster |

**Retourne :** `$this` (fluent interface)

**Exceptions :** Aucune (les erreurs de syntaxe sont propagées par le moteur de cluster)

**Exemple :**
```php
$repository = new UserRepository();
$users = $repository
    ->whereCluster('metadata', 'status=active & age>25')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));
```

---

### `clearClusterFilters(): self`

Supprime tous les filtres cluster précédemment appliqués.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `$this` (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
$repository->whereCluster('metadata', 'status=active');
$countActive = $repository->count(); // 5

$repository->clearClusterFilters();
$allCount = $repository->count(); // 10
```

---

### `info(): RepositoryInfoRecord`

Retourne des informations sur le repository (classes du modèle et du record).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `RepositoryInfoRecord<TModel, TRecord>` - Enregistrement contenant les noms des classes

**Exceptions :** Aucune

**Exemple :**
```php
$info = $repository->info();
echo $info->modelClass;  // 'App\Models\User'
echo $info->recordClass; // 'App\Records\UserRecord'
```

---

### `create(AbstractRecord $record): Model`

Crée un nouveau modèle à partir d'un enregistrement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `TRecord` | Enregistrement contenant les données à créer |

**Retourne :** `TModel` - Le modèle créé

**Exceptions :** `Illuminate\Database\QueryException` - En cas d'erreur de base de données

**Exemple :**
```php
$record = new UserRecord(
    name: 'John Doe',
    email: 'john@example.com',
    status: UserStatus::ACTIVE
);

$user = $repository->create($record);
echo $user->id; // 1
```

---

### `createRaw(array $data): Model`

Crée un nouveau modèle à partir de données brutes (tableau).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Données brutes pour la création |

**Retourne :** `TModel` - Le modèle créé

**Exceptions :** `Illuminate\Database\QueryException` - En cas d'erreur de base de données

**Exemple :**
```php
$user = $repository->createRaw([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'status' => 'active',
]);
```

---

### `find(int|string $id): ?Model`

Trouve un modèle par sa clé primaire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | Identifiant du modèle |

**Retourne :** `TModel|null` - Le modèle trouvé ou `null`

**Exceptions :** Aucune

**Exemple :**
```php
$user = $repository->find(42);
if ($user !== null) {
    echo $user->name;
}
```

---

### `findWithTrashed(int|string $id): ?Model`

Trouve un modèle par sa clé primaire, y compris les modèles soft-deleted.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | Identifiant du modèle |

**Retourne :** `TModel|null` - Le modèle trouvé ou `null`

**Exceptions :** Aucune

**Exemple :**
```php
// Récupère même les utilisateurs supprimés
$user = $repository->findWithTrashed(42);
if ($user !== null && $user->trashed()) {
    echo "L'utilisateur est supprimé";
}
```

---

### `findBy(FindByRecord $record): Collection`

Trouve des modèles correspondant à des critères de recherche.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `FindByRecord` | Critères de recherche, tris et limites |

**Retourne :** `Collection<int, TModel>` - Collection des modèles trouvés

**Exceptions :** Aucune

**Exemple :**
```php
$filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
$sortBy = new SortColumns('created_at:desc');
$columns = new SelectColumns(['id', 'name', 'email']);

$record = new FindByRecord(
    filters: $filters,
    sortBy: $sortBy,
    limit: 10,
    columns: $columns
);

$users = $repository->findBy($record);
foreach ($users as $user) {
    echo $user->name;
}
```

---

### `update(int|string $id, AbstractRecord $record): Model`

Met à jour un modèle avec les données d'un enregistrement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | Identifiant du modèle |
| `$record` | `TRecord` | Enregistrement contenant les données de mise à jour |

**Retourne :** `TModel` - Le modèle mis à jour

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
$record = new UserRecord(name: 'Updated Name');
$user = $repository->update(1, $record);
echo $user->name; // 'Updated Name'
```

---

### `updateRaw(int|string $id, array $data): Model`

Met à jour un modèle avec des données brutes. Utile pour définir des valeurs `NULL`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | Identifiant du modèle |
| `$data` | `array<string, mixed>` | Données brutes de mise à jour |

**Retourne :** `TModel` - Le modèle mis à jour

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
// Met à jour uniquement le nom
$user = $repository->updateRaw(1, ['name' => 'John Updated']);

// Définit l'email à NULL
$user = $repository->updateRaw(1, ['email' => null]);
```

---

### `delete(int|string $id): bool`

Supprime un modèle (soft delete si le trait `SoftDeletes` est utilisé).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | Identifiant du modèle |

**Retourne :** `bool` - `true` si supprimé, `false` si non trouvé

**Exceptions :** Aucune

**Exemple :**
```php
$deleted = $repository->delete(42);
if ($deleted) {
    echo "L'utilisateur a été supprimé";
}
```

---

### `restore(int|string $id): bool`

Restaure un modèle soft-deleted.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | Identifiant du modèle |

**Retourne :** `bool` - `true` si restauré, `false` sinon

**Exceptions :** Aucune

**Exemple :**
```php
$restored = $repository->restore(42);
if ($restored) {
    echo "L'utilisateur a été restauré";
}
```

---

### `forceDelete(int|string $id): bool`

Supprime définitivement un modèle (hard delete).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | Identifiant du modèle |

**Retourne :** `bool` - `true` si supprimé, `false` si non trouvé

**Exceptions :** Aucune

**Exemple :**
```php
$deleted = $repository->forceDelete(42);
if ($deleted) {
    echo "L'utilisateur a été définitivement supprimé";
}
```

---

### `forceDeleteBulk(AbstractRecord $criteria): int`

Supprime définitivement plusieurs modèles correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord` | Critères de sélection |

**Retourne :** `int` - Nombre de modèles supprimés

**Exceptions :** Aucune

**Exemple :**
```php
$criteria = new UserFiltersRecord(status: UserStatus::INACTIVE);
$count = $repository->forceDeleteBulk($criteria);
echo "$count utilisateurs inactifs supprimés";
```

---

### `count(?AbstractRecord $criteria = null): int`

Compte les modèles correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord|null` | Critères optionnels |

**Retourne :** `int` - Nombre de modèles

**Exceptions :** Aucune

**Exemple :**
```php
// Compte tous les utilisateurs
$total = $repository->count();

// Compte les utilisateurs actifs
$criteria = new UserFiltersRecord(status: UserStatus::ACTIVE);
$activeCount = $repository->count($criteria);
```

---

### `exists(AbstractRecord $criteria): bool`

Vérifie si au moins un modèle correspond aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord` | Critères de vérification |

**Retourne :** `bool` - `true` si un modèle existe

**Exceptions :** Aucune

**Exemple :**
```php
$criteria = new UserFiltersRecord(email: 'john@example.com');
if ($repository->exists($criteria)) {
    echo "L'utilisateur existe déjà";
}
```

---

### `paginate(PaginateRecord $record): LengthAwarePaginator`

Paginer les résultats correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `PaginateRecord` | Configuration de pagination |

**Retourne :** `LengthAwarePaginator<TModel>` - Résultats paginés

**Exceptions :** Aucune

**Exemple :**
```php
$filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
$columns = new SelectColumns(['id', 'name', 'email']);

$record = new PaginateRecord(
    perPage: 15,
    page: 2,
    filters: $filters,
    columns: $columns,
    sortBy: 'created_at',
    sortDir: SortDir::DESC
);

$paginator = $repository->paginate($record);
echo "Page {$paginator->currentPage()} sur {$paginator->lastPage()}";
```

---

### `deleteBulk(AbstractRecord $criteria): int`

Supprime plusieurs modèles correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord` | Critères de sélection |

**Retourne :** `int` - Nombre de modèles supprimés

**Exceptions :** Aucune

**Exemple :**
```php
$criteria = new UserFiltersRecord(role: UserRole::GUEST);
$count = $repository->deleteBulk($criteria);
echo "$count utilisateurs invités supprimés";
```

---

## Cas d'utilisation

### Cas 1 : Repository utilisateur avec filtres avancés

**Problème :** Implémenter un repository pour les utilisateurs avec des filtres de recherche et des soft deletes.

**Solution :** Étendre `AbstractRepository` et implémenter `applyFilters()`

```php
<?php

declare(strict_types=1);

use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(
            User::class,
            UserRecord::class
        );
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if ($filters instanceof UserFiltersRecord) {
            if ($filters->name !== null) {
                $query->where('name', 'like', "%{$filters->name}%");
            }
            
            if ($filters->email !== null) {
                $query->where('email', $filters->email);
            }
            
            if ($filters->status !== null) {
                $query->where('status', $filters->status);
            }
        }
    }
}

// Utilisation
$repository = new UserRepository();
$filters = new UserFiltersRecord(
    name: 'John',
    status: UserStatus::ACTIVE
);

$users = $repository->findBy(
    new FindByRecord(filters: $filters)
);
```

---

### Cas 2 : Filtrage JSON avec cluster

**Problème :** Rechercher des utilisateurs avec des métadonnées complexes stockées en JSON.

**Solution :** Utiliser `whereCluster()` pour interroger la colonne JSON.

```php
// Recherche des utilisateurs ayant un statut 'active' et le rôle 'admin'
$users = $repository
    ->whereCluster('metadata', 'status=active & role=admin')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// Recherche avec agrégation
$users = $repository
    ->whereCluster('metadata', 'COUNT(addresses) > 2')
    ->paginate(new PaginateRecord(
        perPage: 10,
        page: 1,
        filters: new EmptyRecord()
    ));

// Recherche avec chemin de tableau
$users = $repository
    ->whereCluster('metadata', 'addresses[city=Kinshasa]')
    ->count(); // 5
```

---

### Cas 3 : Mise à jour sélective avec gestion des NULL

**Problème :** Mettre à jour uniquement certains champs tout en permettant de définir des valeurs `NULL`.

**Solution :** Utiliser `update()` avec un record partiel et `updateRaw()` pour définir des `NULL`.

```php
// Mise à jour partielle avec un record
$partialRecord = new UserRecord(
    name: 'Updated Name',
    // Les autres champs restent null et sont ignorés
);
$user = $repository->update(1, $partialRecord);

// Définition explicite de NULL
$user = $repository->updateRaw(1, [
    'email' => null, // Supprime l'email
    'status' => UserStatus::SUSPENDED->value,
]);
```

---

### Cas 4 : Gestion des soft deletes

**Problème :** Gérer la suppression et la restauration des modèles.

**Solution :** Utiliser les méthodes dédiées `delete()`, `restore()`, et `forceDelete()`.

```php
// Soft delete
$repository->delete(42);

// Vérifier si le modèle est supprimé
$user = $repository->findWithTrashed(42);
if ($user !== null && $user->trashed()) {
    echo "L'utilisateur est supprimé";
}

// Restaurer
$restored = $repository->restore(42);
if ($restored) {
    echo "L'utilisateur a été restauré";
}

// Suppression définitive
$repository->forceDelete(42);

// Suppression définitive en masse
$criteria = new UserFiltersRecord(status: UserStatus::INACTIVE);
$count = $repository->forceDeleteBulk($criteria);
echo "$count utilisateurs supprimés définitivement";
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle non trouvé pour `update()` | `ModelNotFoundException` | `"Model [ModelClass] with ID [id] not found"` |
| Modèle non trouvé pour `updateRaw()` | `ModelNotFoundException` | `"Model [ModelClass] with ID [id] not found"` |
| Erreur de base de données | `Illuminate\Database\QueryException` | Message SQL personnalisé |
| Syntaxe de cluster invalide | `InvalidArgumentException` | `"Invalid cluster query syntax: [query]"` |

## Intégration

### Avec Laravel
- Utilise `Illuminate\Database\Eloquent\Model` pour les entités
- Utilise `Illuminate\Contracts\Pagination\LengthAwarePaginator` pour la pagination
- Supporte le trait `Illuminate\Database\Eloquent\SoftDeletes`

### Avec DomainStructures
- Utilise `AbstractRecord` pour les transferts de données
- Utilise `EmptyRecord` comme enregistrement vide
- S'intègre avec `RepositoryInfoRecord`, `FindByRecord`, `PaginateRecord`

### Avec LaravelCluster
- Utilise la méthode `whereCluster()` sur le query builder
- Supporte les requêtes complexes sur colonnes JSON

## Performance

- **Complexité :** O(n) pour les opérations CRUD, O(1) pour les find par ID
- **Cache :** Aucun cache intégré, peut être ajouté via des décorateurs
- **Hydratation :** Les records sont hydratés automatiquement
- **Requêtes :** Les filtres cluster peuvent être lourds sur de grands jeux de données

### Optimisations recommandées
1. Ajouter des index sur les colonnes JSON fréquemment filtrées
2. Utiliser `select()` pour limiter les colonnes récupérées
3. Utiliser `paginate()` plutôt que `findBy()` pour les gros volumes
4. Éviter les filtres cluster trop complexes sur de grandes tables

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.1+ | ✅ Complet | Support complet des types |
| PHP 8.2+ | ✅ Complet | Support des readonly classes |
| PHP 8.3+ | ✅ Complet | Support des types dynamiques |

| Version Laravel | Support | Notes |
|-----------------|---------|-------|
| Laravel 11+ | ✅ Complet | Support complet |
| Laravel 12+ | ✅ Complet | Support complet |
| Laravel 13+ | ✅ Complet | Support complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\Records\SelectColumns;
use AndyDefer\Repository\Records\SortColumns;
use Illuminate\Database\Eloquent\Builder;

final class ProductRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(
            Product::class,
            ProductRecord::class
        );
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if ($filters instanceof ProductFiltersRecord) {
            if ($filters->name !== null) {
                $query->where('name', 'like', "%{$filters->name}%");
            }
            
            if ($filters->min_price !== null) {
                $query->where('price', '>=', $filters->min_price);
            }
            
            if ($filters->max_price !== null) {
                $query->where('price', '<=', $filters->max_price);
            }
            
            if ($filters->is_active !== null) {
                $query->where('is_active', $filters->is_active);
            }
        }
    }
}

// Utilisation complète
$repository = new ProductRepository();

// Création
$product = $repository->create(new ProductRecord(
    name: 'Laptop Pro',
    price: 999.99,
    stock: 25,
    is_active: true
));

// Recherche avec filtres cluster et sélection de colonnes
$filters = new ProductFiltersRecord(
    min_price: 500.00,
    is_active: true
);

$sortBy = new SortColumns('price:asc|created_at:desc');
$columns = new SelectColumns(['id', 'name', 'price', 'stock']);

$record = new FindByRecord(
    filters: $filters,
    sortBy: $sortBy,
    limit: 20,
    columns: $columns
);

$products = $repository
    ->whereCluster('metadata', 'category=laptop & stock>10')
    ->findBy($record);

// Pagination
$paginator = $repository->paginate(new PaginateRecord(
    perPage: 15,
    page: 2,
    filters: $filters,
    columns: $columns,
    sortBy: 'price',
    sortDir: SortDir::DESC
));

// Mise à jour et suppression
$updated = $repository->update(1, new ProductRecord(
    price: 899.99,
    stock: 30
));

$repository->delete(2);

// Comptage
$activeCount = $repository->count(
    new ProductFiltersRecord(is_active: true)
);
```

## Voir aussi

- `AbstractRecord` - Structure de données immuable pour le transfert
- `FindByRecord` - Configuration de recherche et de tri
- `PaginateRecord` - Configuration de pagination
- `RepositoryInfoRecord` - Informations du repository
- `ModelNotFoundException` - Exception de modèle non trouvé
- `LaravelCluster` - Moteur de requêtes JSON complexes