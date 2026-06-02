# AbstractRepository - Référence Technique

## Description

Classe abstraite qui implémente les opérations CRUD de base pour l'accès aux données entre des Records (value objects) et des modèles Eloquent.

## Hiérarchie

```
AbstractRepositoryInterface<TModel, TRecord>
    └── AbstractRepository<TModel, TRecord>
```

**Interfaces implémentées :** `AbstractRepositoryInterface`

## Rôle principal

Assure la conversion automatique entre les `AbstractRecord` (couche domaine) et les modèles Eloquent (couche infrastructure). Fournit une base standardisée pour toutes les opérations de persistance avec gestion des types génériques.

## API / Méthodes publiques

### `__construct(string $modelClass, string $recordClass)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `class-string<TModel>` | Nom complet de la classe du modèle Eloquent |
| `$recordClass` | `class-string<TRecord>` | Nom complet de la classe du Record associé |

**Retourne :** `void`

**Exemple :**
```php
$repository = new UserRepository(
    User::class,
    UserRecord::class
);
```

### `info(): RepositoryInfoRecord`

Retourne un enregistrement d'informations sur le repository.

**Retourne :** `RepositoryInfoRecord<TModel, TRecord>` - Enregistrement contenant les classes du modèle et du record

**Exemple :**
```php
$info = $repository->info();
echo $info->modelClass;    // User::class
echo $info->recordClass;   // UserRecord::class
```

### `create(AbstractRecord $record): Model`

Crée un nouveau modèle à partir d'un record.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `TRecord` | Record contenant les données à persister |

**Retourne :** `TModel` - Le modèle créé avec son ID généré

**Exceptions :** Aucune exception spécifique (délégue à Eloquent)

**Exemple :**
```php
$record = new UserRecord(name: 'John Doe', email: 'john@example.com');
$user = $repository->create($record);
echo $user->id; // 1
```

### `find(int $id): ?Model`

Recherche un modèle par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant unique du modèle |

**Retourne :** `TModel|null` - Le modèle trouvé ou null

**Exemple :**
```php
$user = $repository->find(1);
if ($user !== null) {
    echo $user->name;
}
```

### `findBy(FindByRecord $record): Collection`

Recherche des modèles selon des critères complexes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `FindByRecord` | Enregistrement contenant filtres, tri, limite, colonnes |

**Retourne :** `Collection<int, TModel>` - Collection de modèles

**Exemple :**
```php
$filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
$columns = new SelectColumns(['id', 'name', 'email']);

$findByRecord = new FindByRecord(
    filters: $filters,
    limit: 10,
    sortBy: 'name',
    sortDir: SortDirection::ASC,
    columns: $columns
);

$users = $repository->findBy($findByRecord);
```

### `update(int $id, AbstractRecord $record): Model`

Met à jour un modèle existant.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant du modèle à mettre à jour |
| `$record` | `TRecord` | Record contenant les nouvelles données |

**Retourne :** `TModel` - Le modèle mis à jour et rafraîchi

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
$updateRecord = new UserRecord(name: 'Jane Doe');
$user = $repository->update(1, $updateRecord);
echo $user->name; // 'Jane Doe'
```

### `delete(int $id): bool`

Supprime un modèle par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant du modèle à supprimer |

**Retourne :** `bool` - True si supprimé, false si non trouvé

**Exemple :**
```php
if ($repository->delete(1)) {
    echo 'User deleted successfully';
}
```

### `count(?AbstractRecord $criteria = null): int`

Compte le nombre de modèles correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord|null` | Critères de filtrage (null pour tous) |

**Retourne :** `int` - Nombre total de modèles

**Exemple :**
```php
$total = $repository->count(); // Tous les utilisateurs

$criteria = new UserFiltersRecord(status: UserStatus::ACTIVE);
$activeCount = $repository->count($criteria);
```

### `exists(AbstractRecord $criteria): bool`

Vérifie l'existence d'au moins un modèle correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord` | Critères de recherche |

**Retourne :** `bool` - True si au moins un modèle existe

**Exemple :**
```php
$criteria = new UserFiltersRecord(email: 'john@example.com');
if ($repository->exists($criteria)) {
    echo 'Email already exists';
}
```

### `paginate(PaginateRecord $record): LengthAwarePaginator`

Récupère des résultats paginés selon les critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `PaginateRecord` | Configuration de pagination (page, limite, tri, filtres) |

**Retourne :** `LengthAwarePaginator<TModel>` - Instance du paginateur Laravel

**Exemple :**
```php
$paginateRecord = new PaginateRecord(
    perPage: 15,
    page: 2,
    sortBy: 'created_at',
    sortDir: SortDirection::DESC,
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE)
);

$users = $repository->paginate($paginateRecord);
foreach ($users as $user) {
    echo $user->name;
}
echo $users->links(); // Liens de pagination
```

### `deleteBulk(AbstractRecord $criteria): int`

Supprime plusieurs modèles correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord` | Critères pour sélectionner les modèles à supprimer |

**Retourne :** `int` - Nombre d'enregistrements supprimés

**Exemple :**
```php
$criteria = new UserFiltersRecord(status: UserStatus::INACTIVE);
$deletedCount = $repository->deleteBulk($criteria);
echo "Deleted {$deletedCount} inactive users";
```

## Cas d'utilisation

### Cas 1 : Repository utilisateur standard

```php
<?php

declare(strict_types=1);

use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(User::class, UserRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (!$filters instanceof UserFiltersRecord) {
            return;
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->minAge !== null) {
            $query->whereRaw('YEAR(NOW()) - YEAR(birth_date) >= ?', [$filters->minAge]);
        }
    }
}
```

### Cas 2 : Recherche avancée avec colonnes spécifiques

```php
// Rechercher uniquement les noms et emails des utilisateurs actifs
$filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
$columns = new SelectColumns(['id', 'name', 'email']);

$findByRecord = new FindByRecord(
    filters: $filters,
    columns: $columns,
    sortBy: 'name',
    sortDir: SortDirection::ASC
);

$users = $userRepository->findBy($findByRecord);
foreach ($users as $user) {
    // Seuls id, name, email sont chargés
    echo "{$user->name} ({$user->email})\n";
}
```

### Cas 3 : Mise à jour partielle

```php
// Seul le champ name sera mis à jour
$updateRecord = new UserRecord(
    name: 'New Name',
    // email et autres champs sont null (ignorés)
);

$user = $userRepository->update(1, $updateRecord);
// L'email reste inchangé
```

## Méthodes protégées

### `buildQuery(AbstractRecord $filters): Builder<TModel>`

Construit la requête Eloquent avec les filtres appliqués.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$filters` | `AbstractRecord` | Record contenant les filtres à appliquer |

**Retourne :** `Builder<TModel>` - Query builder configuré

**Comportement :**
- Retourne une nouvelle instance de query
- Ignore les filtres si c'est un `EmptyRecord`
- Délègue l'application des filtres à `applyFilters()`

### `applyFilters(Builder $query, AbstractRecord $filters): void`

Méthode abstraite à implémenter dans les repositories concrets.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder<TModel>` | Query builder à configurer |
| `$filters` | `AbstractRecord` | Record contenant les critères |

**À implémenter :** Logique spécifique de filtrage selon la structure du record

## Flux d'exécution

```
Request → Repository
    ├── create() → Record → toArrayWithoutNulls() → Model::create()
    ├── find() → Model::find()
    ├── findBy() → buildQuery() → applyFilters() → select()/orderBy()/limit() → get()
    ├── update() → find() → array_filter() → update() → refresh()
    ├── delete() → find() → delete()
    ├── count() → buildQuery() → count()
    ├── exists() → buildQuery() → exists()
    ├── paginate() → buildQuery() → paginate()
    └── deleteBulk() → buildQuery() → delete()
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle non trouvé lors de l'update | `ModelNotFoundException` | `{ModelClass} with id {id} not found` |
| Erreur de validation Eloquent | Exception Eloquent native | Dépend du modèle |
| Connexion base de données | `PDOException` | Dépend du pilote |

## Intégration

**Avec Eloquent :**
- Utilise les Query Builder Eloquent pour toutes les opérations
- Supporte nativement les relations, scopes et events

**Avec les Records :**
- `toArrayWithoutNulls()` pour la création/mise à jour
- Les valeurs null sont ignorées lors des updates

**Avec les Value Objects :**
- `SelectColumns` pour filtrer les colonnes
- `SortDirection` pour le tri (ASC/DESC)

**Avec le système de pagination :**
- Utilise `LengthAwarePaginator` de Laravel
- Compatible avec les vues de pagination Blade

## Performance

**Optimisations :**
- Les requêtes utilisent les indexes standards d'Eloquent
- `array_filter` sur les updates (ignorer les valeurs null)
- Limitation automatique des colonnes via `SelectColumns`
- Pas de chargement des relations par défaut

**Complexité :**
- Opérations CRUD : O(1) pour la logique métier (délégue à la BDD)
- findBy avec conditions : O(n) où n = nombre de filtres
- deleteBulk : O(m) où m = nombre de modèles supprimés

**Points d'attention :**
- Les méthodes `find()` et `update()` effectuent 2 requêtes (SELECT + UPDATE)
- `refresh()` après update recharge le modèle (requête supplémentaire)

## Compatibilité

| Version | Support | Remarques |
|---------|---------|-----------|
| PHP 8.2+ | ✅ Complet | Types génériques, readonly classes |
| PHP 8.1 | ✅ Complet | Enums supportés |
| Laravel 10+ | ✅ Complet | Compatible avec toutes les versions supportées |
| Laravel 9 | ✅ Complet | Tests passés |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Enums\SortDirection;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// 1. Définir le repository concret
final class ProductRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Product::class, ProductRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (!$filters instanceof ProductFiltersRecord) {
            return;
        }

        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }

        if ($filters->minPrice !== null) {
            $query->where('price', '>=', $filters->minPrice);
        }

        if ($filters->inStock === true) {
            $query->where('quantity', '>', 0);
        }
    }
}

// 2. Utiliser le repository
$repository = new ProductRepository();

// Créer un produit
$productRecord = new ProductRecord(
    name: 'Laptop',
    price: 999.99,
    categoryId: 5,
    quantity: 10
);
$product = $repository->create($productRecord);

// Rechercher des produits
$filters = new ProductFiltersRecord(
    categoryId: 5,
    minPrice: 500,
    inStock: true
);
$columns = new SelectColumns(['id', 'name', 'price']);

$findByRecord = new FindByRecord(
    filters: $filters,
    columns: $columns,
    sortBy: 'price',
    sortDir: SortDirection::ASC,
    limit: 20
);

$products = $repository->findBy($findByRecord);

// Paginer les résultats
$paginateRecord = new PaginateRecord(
    perPage: 15,
    page: 1,
    sortBy: 'created_at',
    sortDir: SortDirection::DESC,
    filters: $filters,
    columns: $columns
);

$paginatedProducts = $repository->paginate($paginateRecord);

// Mettre à jour partiellement
$updateRecord = new ProductRecord(price: 899.99);
$updatedProduct = $repository->update($product->id, $updateRecord);

// Suppression en masse
$deleteFilters = new ProductFiltersRecord(inStock: false);
$deletedCount = $repository->deleteBulk($deleteFilters);
```

## Voir aussi

- `AbstractRepositoryInterface` - Interface définissant le contrat
- `FindByRecord` - Configuration pour les recherches
- `PaginateRecord` - Configuration pour la pagination
- `SelectColumns` - Value object pour les colonnes
- `ModelNotFoundException` - Exception levée lors des updates
- `EmptyRecord` - Record vide pour absence de filtres