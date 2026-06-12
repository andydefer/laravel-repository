# AbstractRepository - Référence Technique

## Description

Classe abstraite qui implémente les opérations CRUD de base pour l'accès aux données entre des Records (value objects) et des modèles Eloquent. Supporte nativement le Soft Delete avec des méthodes dédiées.

## Hiérarchie

```
AbstractRepositoryInterface<TModel, TRecord>
    └── AbstractRepository<TModel, TRecord>
```

**Interfaces implémentées :** `AbstractRepositoryInterface`

## Rôle principal

Assure la conversion automatique entre les `AbstractRecord` (couche domaine) et les modèles Eloquent (couche infrastructure). Fournit une base standardisée pour toutes les opérations de persistance avec gestion des types génériques et support intégré du Soft Delete.

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

**Exceptions :** Aucune exception spécifique (délègue à Eloquent)

**Exemple :**
```php
$record = new UserRecord(name: 'John Doe', email: 'john@example.com');
$user = $repository->create($record);
echo $user->id; // 1
```

### `createRaw(array $data): Model`

Crée un nouveau modèle directement à partir d'un tableau de données brutes, sans passer par l'hydratation d'un Record.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Tableau associatif des données à persister |

**Retourne :** `TModel` - Le modèle créé avec son ID généré

**Exceptions :** `QueryException` - Si les données ne respectent pas les contraintes de la base

**Exemple :**
```php
// Création avec données brutes
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active',
];
$user = $repository->createRaw($data);

// Création avec valeurs null explicites
$data = [
    'name' => 'User Without Email',
    'email' => null,
    'status' => 'active',
];
$user = $repository->createRaw($data);
```

### `find(int $id): ?Model`

Recherche un modèle par son ID (exclut les soft deleted par défaut).

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

### `findWithTrashed(int $id): ?Model`

Recherche un modèle par son ID en incluant les soft deleted.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant unique du modèle |

**Retourne :** `TModel|null` - Le modèle trouvé (même s'il est soft deleted) ou null

**Exemple :**
```php
// Récupérer un utilisateur même s'il est supprimé
$deletedUser = $repository->findWithTrashed(1);
if ($deletedUser !== null && $deletedUser->trashed()) {
    echo "User is deleted since: {$deletedUser->deleted_at}";
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

// Tri simple
$findByRecord = new FindByRecord(
    filters: $filters,
    limit: 10,
    sortBy: new SortColumns('name:asc'),
    columns: $columns
);

// Tri multi-colonnes
$findByRecord = new FindByRecord(
    filters: $filters,
    limit: 10,
    sortBy: new SortColumns('name:asc|created_at:desc|id:asc'),
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

### `updateRaw(int $id, array $data): Model`

Met à jour un modèle avec des données brutes. Utile pour définir des champs à NULL ou utiliser des valeurs spécifiques à la base de données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant du modèle à mettre à jour |
| `$data` | `array<string, mixed>` | Tableau brut de données |

**Retourne :** `TModel` - Le modèle mis à jour

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
// Mettre à jour le nom et définir l'email à NULL
$updated = $repository->updateRaw(1, [
    'name' => 'New Name',
    'email' => null,
]);
```

### `delete(int $id): bool`

Supprime un modèle par son ID (soft delete si le modèle utilise le trait `SoftDeletes`, sinon hard delete).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant du modèle à supprimer |

**Retourne :** `bool` - True si supprimé, false si non trouvé

**Exemple :**
```php
// Suppression (soft delete si SoftDeletes est utilisé)
if ($repository->delete(1)) {
    echo 'Product deleted successfully';
}
```

### `restore(int $id): bool`

Restaure un modèle soft deleté.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant du modèle à restaurer |

**Retourne :** `bool` - True si restauré, false si non trouvé ou non soft deleté

**⚠️ Important :** Ne fonctionne que si le modèle utilise le trait `SoftDeletes`.

**Exemple :**
```php
// Restaurer un produit supprimé
if ($repository->restore(1)) {
    echo 'Product restored successfully';
}
```

### `forceDelete(int $id): bool`

Supprime définitivement un modèle (hard delete), même s'il est déjà soft deleté.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | Identifiant du modèle à supprimer définitivement |

**Retourne :** `bool` - True si supprimé, false si non trouvé

**⚠️ Important :** Ne fonctionne que si le modèle utilise le trait `SoftDeletes`. Cette opération est irréversible.

**Exemple :**
```php
// Suppression définitive (hard delete)
if ($repository->forceDelete(1)) {
    echo 'Product permanently deleted';
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
    sortBy: new SortColumns('created_at:desc'),
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE)
);

$users = $repository->paginate($paginateRecord);
foreach ($users as $user) {
    echo $user->name;
}
echo $users->links(); // Liens de pagination
```

### `deleteBulk(AbstractRecord $criteria): int`

Supprime plusieurs modèles correspondant aux critères (soft delete si le modèle utilise `SoftDeletes`, sinon hard delete).

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

### `forceDeleteBulk(AbstractRecord $criteria): int`

Supprime définitivement plusieurs modèles correspondant aux critères (hard delete), même s'ils sont soft deletés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$criteria` | `AbstractRecord` | Critères pour sélectionner les modèles à supprimer définitivement |

**Retourne :** `int` - Nombre d'enregistrements supprimés définitivement

**⚠️ Important :** Cette opération est irréversible. Si le modèle utilise `SoftDeletes`, les enregistrements sont définitivement supprimés de la base de données.

**Exemple :**
```php
// Supprimer définitivement tous les utilisateurs inactifs
$criteria = new UserFiltersRecord(status: UserStatus::INACTIVE);
$deletedCount = $repository->forceDeleteBulk($criteria);
echo "Permanently deleted {$deletedCount} inactive users";

// Supprimer définitivement tous les soft deleted
$criteria = new ProductFiltersRecord(is_deleted: true);
$deletedCount = $repository->forceDeleteBulk($criteria);
echo "Permanently deleted {$deletedCount} soft deleted products";
```

## Cas d'utilisation

### Cas 1 : Repository utilisateur standard (sans SoftDelete)

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

### Cas 2 : Repository produit avec SoftDelete

```php
<?php

declare(strict_types=1);

use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

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

        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        // Filtre pour inclure ou exclure les soft deleted
        if ($filters->include_deleted === true) {
            $query->withTrashed();
        } elseif ($filters->include_deleted === false) {
            $query->withoutTrashed();
        }
    }
}
```

### Cas 3 : Gestion des soft deleted dans les recherches

```php
// Récupérer uniquement les produits actifs (non supprimés)
$filters = new ProductFiltersRecord(is_active: true);
$activeProducts = $repository->findBy(new FindByRecord(filters: $filters));

// Récupérer tous les produits (y compris supprimés)
$allProducts = $repository->findWithTrashed(1);

// Restaurer un produit supprimé
if ($repository->restore(5)) {
    echo "Product restored";
}

// Suppression définitive d'un produit
$repository->forceDelete(5);

// Suppression définitive de tous les soft deleted
$filters = new ProductFiltersRecord(is_deleted: true);
$repository->forceDeleteBulk($filters);
```

### Cas 4 : Recherche avancée avec colonnes spécifiques et tri multi-colonnes

```php
// Rechercher uniquement les noms et emails des utilisateurs actifs
$filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
$columns = new SelectColumns(['id', 'name', 'email']);

// Tri simple
$findByRecord = new FindByRecord(
    filters: $filters,
    columns: $columns,
    sortBy: new SortColumns('name:asc')
);

// Tri multi-colonnes
$findByRecord = new FindByRecord(
    filters: $filters,
    columns: $columns,
    sortBy: new SortColumns('name:asc|created_at:desc')
);

$users = $userRepository->findBy($findByRecord);
foreach ($users as $user) {
    // Seuls id, name, email sont chargés
    echo "{$user->name} ({$user->email})\n";
}
```

### Cas 5 : Mise à jour partielle

```php
// Seul le champ name sera mis à jour
$updateRecord = new UserRecord(
    name: 'New Name',
    // email et autres champs sont null (ignorés)
);

$user = $userRepository->update(1, $updateRecord);
// L'email reste inchangé
```

### Cas 6 : Utilisation de `createRaw` pour OTP service

```php
private function createOtpModel(
    Model $otpable,
    OtpProcessingContext $context,
    ?array $channels,
    ?array $metadata,
    int $expiresInMinutes,
    int $maxAttempts,
    string $plainCode,
): OneTimePassword {
    $data = [
        'otpable_type' => $otpable->getMorphClass(),
        'otpable_id' => $otpable->getKey(),
        'token_hash' => $this->hash->make($plainCode),
        'type' => $context->getType(),
        'destination' => $context->getDestination(),
        'channels' => $channels,
        'meta' => $metadata,
        'max_attempts' => $maxAttempts,
        'expires_at' => now()->addMinutes($expiresInMinutes),
    ];

    return $this->otpRepository->createRaw($data);
}
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

### `usesSoftDeletes(): bool` (privée)

Vérifie si le modèle utilise le trait `SoftDeletes`.

**Retourne :** `bool` - True si le modèle utilise SoftDeletes

## Flux d'exécution

```
Request → Repository
    ├── create() → Record → toArrayWithoutNulls() → Model::create()
    ├── createRaw() → array → Model::create()
    ├── find() → Model::find()
    ├── findWithTrashed() → withTrashed() → Model::find()
    ├── findBy() → buildQuery() → applyFilters() → select()/orderBy()/limit() → get()
    │   └── orderBy() → Pour chaque colonne dans SortColumns (ordre multiple)
    ├── update() → find() → array_filter() → update() → refresh()
    ├── updateRaw() → find() → update() → refresh()
    ├── delete() → find() → delete() (soft ou hard selon le modèle)
    ├── restore() → findWithTrashed() → model->restore()
    ├── forceDelete() → findWithTrashed() → model->forceDelete()
    ├── deleteBulk() → buildQuery() → delete() (soft ou hard selon le modèle)
    ├── forceDeleteBulk() → buildQuery() → forceDelete() (hard delete permanent)
    ├── count() → buildQuery() → count()
    ├── exists() → buildQuery() → exists()
    └── paginate() → buildQuery() → paginate()
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle non trouvé lors de l'update | `ModelNotFoundException` | `{ModelClass} with id {id} not found` |
| Modèle non trouvé lors du updateRaw | `ModelNotFoundException` | `{ModelClass} with id {id} not found` |
| Erreur de validation Eloquent | Exception Eloquent native | Dépend du modèle |
| Connexion base de données | `PDOException` | Dépend du pilote |

## Intégration

**Avec Eloquent :**
- Utilise les Query Builder Eloquent pour toutes les opérations
- Supporte nativement les relations, scopes et events
- Détection automatique du trait `SoftDeletes`

**Avec les Records :**
- `toArrayWithoutNulls()` pour la création/mise à jour
- Les valeurs null sont ignorées lors des updates

**Avec les Value Objects :**
- `SelectColumns` pour filtrer les colonnes
- `SortColumns` pour le tri simple ou multi-colonnes (ex: `'name:asc|created_at:desc'`)
- Plusieurs colonnes de tri sont supportées via une syntaxe à barre verticale

**Avec le système de pagination :**
- Utilise `LengthAwarePaginator` de Laravel
- Compatible avec les vues de pagination Blade

## Performance

**Optimisations :**
- Les requêtes utilisent les indexes standards d'Eloquent
- `array_filter` sur les updates (ignorer les valeurs null)
- Limitation automatique des colonnes via `SelectColumns`
- Pas de chargement des relations par défaut
- Détection automatique de SoftDeletes sans overhead inutile
- Tri multi-colonnes appliqué en une seule passe de requête

**Complexité :**
- Opérations CRUD : O(1) pour la logique métier (délègue à la BDD)
- findBy avec conditions : O(n) où n = nombre de filtres
- Tri multi-colonnes : O(k) où k = nombre de colonnes de tri
- deleteBulk / forceDeleteBulk : O(m) où m = nombre de modèles supprimés
- restore/forceDelete : O(1) avec détection de trait

**Points d'attention :**
- Les méthodes `find()` et `update()` effectuent 2 requêtes (SELECT + UPDATE)
- `refresh()` après update recharge le modèle (requête supplémentaire)
- `findWithTrashed()` ajoute `withTrashed()` si le modèle utilise SoftDeletes
- `forceDeleteBulk()` supprime définitivement - opération irréversible
- Le tri multi-colonnes peut impacter les performances sur de très grands jeux de données sans indexes appropriés

## Compatibilité

| Version | Support | Remarques |
|---------|---------|-----------|
| PHP 8.2+ | ✅ Complet | Types génériques, readonly classes |
| PHP 8.1 | ✅ Complet | Enums supportés |
| Laravel 10+ | ✅ Complet | Support complet de SoftDeletes |
| Laravel 9 | ✅ Complet | Tests passés |

## Exemple complet avec SoftDelete, tri multi-colonnes et createRaw

```php
<?php

declare(strict_types=1);

use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

// 1. Modèle avec SoftDelete
final class Product extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['name', 'price', 'category_id', 'quantity'];
}

// 2. Définir le repository concret
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
        
        // Gestion des soft deleted dans les filtres
        if ($filters->includeDeleted === true) {
            $query->withTrashed();
        } elseif ($filters->includeDeleted === false) {
            $query->withoutTrashed();
        }
    }
}

// 3. Utiliser le repository
$repository = new ProductRepository();

// Création avec create (via Record)
$productRecord = new ProductRecord(
    name: 'Laptop',
    price: 999.99,
    categoryId: 5,
    quantity: 10
);
$product = $repository->create($productRecord);

// Création avec createRaw (données brutes)
$rawData = [
    'name' => 'Mouse',
    'price' => 29.99,
    'category_id' => 5,
    'quantity' => 100,
    'is_active' => true,
];
$mouse = $repository->createRaw($rawData);

// Soft delete du produit
$repository->delete($product->id);

// Vérifier que le produit est soft deleté
$deletedProduct = $repository->findWithTrashed($product->id);
if ($deletedProduct && $deletedProduct->trashed()) {
    echo "Product is deleted since: {$deletedProduct->deleted_at}";
}

// Restaurer le produit
if ($repository->restore($product->id)) {
    echo "Product restored!";
}

// Suppression définitive
$repository->forceDelete($product->id);

// Nettoyage de masse : supprimer définitivement tous les soft deleted
$filters = new ProductFiltersRecord(includeDeleted: true);
$deletedCount = $repository->forceDeleteBulk($filters);
echo "Permanently deleted {$deletedCount} products";

// Rechercher des produits (exclut les soft deleted par défaut) avec tri multiple
$filters = new ProductFiltersRecord(
    categoryId: 5,
    minPrice: 500,
    inStock: true,
    includeDeleted: false
);
$columns = new SelectColumns(['id', 'name', 'price']);

// Tri simple
$findByRecord = new FindByRecord(
    filters: $filters,
    columns: $columns,
    sortBy: new SortColumns('price:asc'),
    limit: 20
);

// Tri multi-colonnes
$findByRecordMultiSort = new FindByRecord(
    filters: $filters,
    columns: $columns,
    sortBy: new SortColumns('price:asc|name:desc|id:asc'),
    limit: 20
);

$products = $repository->findBy($findByRecordMultiSort);

// Pagination avec tri multiple
$paginateRecord = new PaginateRecord(
    perPage: 15,
    page: 1,
    sortBy: new SortColumns('category_id:asc|price:desc'),
    filters: $filters
);

$paginatedProducts = $repository->paginate($paginateRecord);
```

## Voir aussi

- `AbstractRepositoryInterface` - Interface définissant le contrat
- `FindByRecord` - Configuration pour les recherches
- `PaginateRecord` - Configuration pour la pagination
- `SelectColumns` - Value object pour les colonnes
- `SortColumns` - Value object pour le tri simple ou multi-colonnes
- `ModelNotFoundException` - Exception levée lors des updates
- `EmptyRecord` - Record vide pour absence de filtres
- `SoftDeletes` - Trait Laravel pour le soft delete