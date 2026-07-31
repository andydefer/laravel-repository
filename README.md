# Laravel Repository

**Une implémentation légère et typée du pattern Repository pour Laravel avec intégration Records et Eloquent.**

[![Version PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Version Laravel](https://img.shields.io/badge/Laravel-12.x%20|%2013.x%20|%2014.x%20|%2015.x-blue)](https://laravel.com)
[![Licence](https://img.shields.io/badge/Licence-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Concepts fondamentaux](#concepts-fondamentaux)
3. [Créer votre premier Repository](#créer-votre-premier-repository)
4. [Référence de l'API](#référence-de-lapi)
5. [Méthodes à surcharger](#méthodes-à-surcharger)
6. [Bonnes pratiques](#bonnes-pratiques)
7. [Exemple complet avec filtres complexes](#exemple-complet-avec-filtres-complexes)
8. [Licence](#licence)

---

## Installation

```bash
composer require andydefer/laravel-repository
```

### Prérequis

- PHP 8.1 ou supérieur
- Laravel 12.x, 13.x, 14.x ou 15.x
- Dépendances automatiques :
  - `andydefer/domain-structures` (structures typées)
  - `andydefer/laravel-cluster` (filtres JSON avancés)
  - `laravel/framework`

---

## Concepts fondamentaux

### Le Record

Un Record est un DTO typé qui sert d'interface entre votre code et le Repository.

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?UserStatus $status = null,
    ) {}
}
```

**Règles pour les Records :**
- ✅ Étendre `AbstractRecord`
- ✅ Propriétés `public readonly`
- ✅ Les champs optionnels = `null` par défaut
- ❌ Pas de logique métier
- ❌ Pas de tableaux bruts (utiliser `TypedCollection`)

### Filtres Cluster sur colonnes JSON

Le package intègre `laravel-cluster` pour interroger des colonnes JSON avec une syntaxe puissante :

```php
// Conditions simples
$users = $repository
    ->whereCluster('metadata', 'status=active')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// Conditions combinées
$users = $repository
    ->whereCluster('metadata', 'status=active & age>25')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// Chemins de tableau
$users = $repository
    ->whereCluster('metadata', 'addresses[city=Kinshasa]')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// Fonctions d'agrégation
$users = $repository
    ->whereCluster('metadata', 'COUNT(addresses) > 2')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// Conditions OR
$users = $repository
    ->whereCluster('metadata', 'status=active | status=suspended')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));
```

### Records de configuration

Le package fournit des Records standardisés pour les opérations :

#### FindByRecord

```php
use AndyDefer\Repository\Records\FindByRecord;

// Tri simple
$findBy = new FindByRecord(
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    limit: 10,
    sortBy: new SortColumns('name:asc'),
    columns: new SelectColumns(['id', 'name', 'email']),
);

// Tri multi-colonnes
$findBy = new FindByRecord(
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    limit: 10,
    sortBy: new SortColumns('name:asc|created_at:desc|id:asc'),
    columns: new SelectColumns(['id', 'name', 'email']),
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `filters` | `AbstractRecord` | `EmptyRecord` | Filtres de recherche |
| `limit` | `?int` | `null` | Limite de résultats |
| `sortBy` | `?SortColumns` | `null` | Colonnes de tri (supporte le multi-colonnes) |
| `columns` | `SelectColumns` | `SelectColumns::all()` | Colonnes à sélectionner |

#### PaginateRecord

```php
use AndyDefer\Repository\Records\PaginateRecord;

$paginate = new PaginateRecord(
    perPage: 15,
    page: 1,
    sortBy: new SortColumns('created_at:desc'),
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    columns: new SelectColumns(['id', 'name', 'email']),
);

// Tri multi-colonnes sur plusieurs champs
$paginate = new PaginateRecord(
    perPage: 15,
    page: 1,
    sortBy: new SortColumns('category_id:asc|price:desc'),
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    columns: new SelectColumns(['id', 'name', 'email']),
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `perPage` | `int` | `15` | Éléments par page |
| `page` | `int` | `1` | Numéro de page |
| `sortBy` | `?SortColumns` | `null` | Colonnes de tri (supporte le multi-colonnes) |
| `filters` | `AbstractRecord` | `EmptyRecord` | Filtres de recherche |
| `columns` | `SelectColumns` | `SelectColumns::all()` | Colonnes à sélectionner |

#### RepositoryInfoRecord

```php
use AndyDefer\Repository\Records\RepositoryInfoRecord;

$info = $repository->info();
// RepositoryInfoRecord {
//     modelClass: 'App\Models\User',
//     recordClass: 'App\Records\UserRecord',
// }
```

### Objet Valeur SortColumns

Le package fournit un Value Object pour gérer le tri simple ou multi-colonnes :

```php
use AndyDefer\Repository\ValueObjects\SortColumns;

// Tri simple
$sort = new SortColumns('name:asc');
$sort->toArray();  // ['name' => 'asc']

// Tri multi-colonnes (syntaxe à barre verticale)
$sort = new SortColumns('name:asc|created_at:desc|id:asc');
$sort->toArray();  // ['name' => 'asc', 'created_at' => 'desc', 'id' => 'asc']

// Depuis un tableau associatif
$sort = SortColumns::fromArray(['name' => 'asc', 'created_at' => 'desc']);

// Vérifications
$sort->hasColumn('name');     // true
$sort->getDirection('name');  // 'asc'
$sort->count();               // 3
```

**Format de chaîne :** `colonne:direction|colonne:direction`
- `direction` peut être `asc` ou `desc`
- Les colonnes sont séparées par le caractère `|` (pipe)

### Objet Valeur SelectColumns

```php
use AndyDefer\Repository\ValueObjects\SelectColumns;

// Créer avec des colonnes spécifiques
$columns = new SelectColumns(['id', 'name', 'email']);

// Sélectionner toutes les colonnes
$allColumns = SelectColumns::all();

// Ajouter des colonnes (retourne une nouvelle instance)
$extended = $columns->add('created_at', 'updated_at');

// Vérifier si une colonne existe
if ($columns->has('email')) {
    // ...
}

// Obtenir le nombre
$count = $columns->count();  // 3

// Convertir en tableau
$array = $columns->toArray();  // ['id', 'name', 'email']
```

---

## Créer votre premier Repository

### 1. Créer le Modèle

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $fillable = ['name', 'email', 'status', 'metadata'];
    
    protected $casts = [
        'metadata' => 'array',
    ];
}
```

### 2. Créer le Record

```php
<?php

namespace App\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?UserStatus $status = null,
        public readonly ?ClusterVO $metadata = null,
    ) {}
}
```

### 3. Créer le Record de filtres (Optionnel)

```php
<?php

namespace App\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class UserFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?UserStatus $status = null,
        public readonly ?string $cluster_query = null, // Pour les filtres JSON
    ) {}
}
```

### 4. Créer le Repository

```php
<?php

namespace App\Repositories;

use AndyDefer\Repository\AbstractRepository;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use App\Models\User;
use App\Records\UserRecord;
use App\Records\UserFiltersRecord;
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

        if ($filters->name !== null) {
            $query->where('name', 'like', '%' . $filters->name . '%');
        }

        if ($filters->email !== null) {
            $query->where('email', 'like', '%' . $filters->email . '%');
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // Filtre cluster sur la colonne JSON
        if ($filters->cluster_query !== null) {
            $query->whereCluster('metadata', $filters->cluster_query);
        }
    }
}
```

### 5. Utiliser le Repository

```php
use App\Repositories\UserRepository;
use App\Records\UserRecord;
use App\Records\UserFiltersRecord;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;

class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {}

    // Créer un utilisateur
    public function createUser(string $name, string $email): User
    {
        return $this->repository->create(new UserRecord(
            name: $name,
            email: $email,
            status: UserStatus::ACTIVE,
            metadata: ClusterVO::fromArray(['preferences' => ['theme' => 'dark']]),
        ));
    }

    // Trouver un utilisateur
    public function findUser(int $id): ?User
    {
        return $this->repository->find($id);
    }

    // Mettre à jour un utilisateur (uniquement les champs non-nuls)
    public function updateUser(int $id, string $name): User
    {
        return $this->repository->update($id, new UserRecord(name: $name));
    }

    // Supprimer un utilisateur
    public function deleteUser(int $id): bool
    {
        return $this->repository->delete($id);
    }

    // Lister avec filtres JSON et tri multiple
    public function listActiveUsersByStatusAndName(): array
    {
        $filters = new UserFiltersRecord(
            status: UserStatus::ACTIVE,
            cluster_query: 'preferences.theme=dark & age>18'
        );
        
        $findBy = new FindByRecord(
            filters: $filters,
            limit: 50,
            sortBy: new SortColumns('status:asc|name:asc'),
        );
        
        return $this->repository->findBy($findBy)->all();
    }

    // Paginer les résultats avec tri multiple
    public function getPaginatedUsers(int $page = 1): LengthAwarePaginator
    {
        $paginate = new PaginateRecord(
            perPage: 15,
            page: $page,
            sortBy: new SortColumns('created_at:desc|id:asc'),
        );
        
        return $this->repository->paginate($paginate);
    }

    // Compter les enregistrements avec filtres
    public function countActiveUsers(): int
    {
        $filters = new UserFiltersRecord(
            status: UserStatus::ACTIVE,
            cluster_query: 'verified=true'
        );
        
        return $this->repository->count($filters);
    }

    // Vérifier l'existence
    public function userExists(string $email): bool
    {
        $filters = new UserFiltersRecord(email: $email);
        return $this->repository->exists($filters);
    }

    // Suppression groupée
    public function deleteInactiveUsers(): int
    {
        $filters = new UserFiltersRecord(status: UserStatus::INACTIVE);
        return $this->repository->deleteBulk($filters);
    }
    
    // Suppression définitive groupée
    public function forceDeleteInactiveUsers(): int
    {
        $filters = new UserFiltersRecord(status: UserStatus::INACTIVE);
        return $this->repository->forceDeleteBulk($filters);
    }
}
```

---

## Référence de l'API

### AbstractRepository

| Méthode | Paramètres | Retour | Description |
|---------|------------|--------|-------------|
| `info()` | - | `RepositoryInfoRecord` | Informations du repository |
| `whereCluster(string $column, string $query)` | `$column, $query` | `$this` | Appliquer un filtre JSON cluster |
| `clearClusterFilters()` | - | `$this` | Supprimer tous les filtres cluster |
| `create(AbstractRecord $record)` | `$record` | `Model` | Créer un nouvel enregistrement |
| `createRaw(array $data)` | `$data` | `Model` | Créer un enregistrement à partir de données brutes |
| `find(int $id)` | `$id` | `Model|null` | Trouver par ID |
| `findWithTrashed(int $id)` | `$id` | `Model|null` | Trouver par ID (inclus soft deleted) |
| `findBy(FindByRecord $record)` | `$record` | `Collection<Model>` | Rechercher avec critères (supporte tri multiple) |
| `update(int $id, AbstractRecord $record)` | `$id, $record` | `Model` | Mettre à jour (champs non-nuls uniquement) |
| `updateRaw(int $id, array $data)` | `$id, $data` | `Model` | Mettre à jour avec données brutes |
| `delete(int $id)` | `$id` | `bool` | Supprimer par ID (soft delete si disponible) |
| `restore(int $id)` | `$id` | `bool` | Restaurer un soft deleted |
| `forceDelete(int $id)` | `$id` | `bool` | Supprimer définitivement |
| `count(?AbstractRecord $criteria)` | `$criteria` | `int` | Compter les enregistrements |
| `exists(AbstractRecord $criteria)` | `$criteria` | `bool` | Vérifier l'existence |
| `paginate(PaginateRecord $record)` | `$record` | `LengthAwarePaginator` | Résultats paginés (supporte tri multiple) |
| `deleteBulk(AbstractRecord $criteria)` | `$criteria` | `int` | Suppression groupée (soft delete si disponible) |
| `forceDeleteBulk(AbstractRecord $criteria)` | `$criteria` | `int` | Suppression définitive groupée |

### Méthodes à surcharger

| Méthode | Description |
|---------|-------------|
| `applyFilters(Builder $query, AbstractRecord $filters)` | Appliquer les filtres de recherche (doit être surchargée) |

### Exceptions

| Exception | Quand |
|-----------|-------|
| `ModelNotFoundException` | `update()` ou `updateRaw()` sur un ID inexistant |
| `InvalidArgumentException` | Nom de colonne invalide dans `SelectColumns` ou `SortColumns` |
| `InvalidArgumentException` | Syntaxe de requête cluster invalide |

---

## Bonnes pratiques

### 1. Un Record par Entité

```php
// ✅ BON
final class UserRecord extends AbstractRecord { ... }
final class PostRecord extends AbstractRecord { ... }

// ❌ MAUVAIS
final class UserPostRecord extends AbstractRecord { ... }
```

### 2. Record de filtres séparé pour les cas complexes

```php
// ✅ BON - Pour les filtres complexes
final class UserFiltersRecord extends AbstractRecord { ... }

// ✅ BON - Pour les cas simples, réutiliser le Record principal
$filters = new UserRecord(status: UserStatus::ACTIVE);
```

### 3. Utiliser des valeurs par défaut pour les champs optionnels

```php
// ✅ BON
public function __construct(
    public readonly ?string $name = null,  // Optionnel
    public readonly string $email,          // Requis
) {}

// ❌ MAUVAIS
public function __construct(
    public readonly ?string $name,
    public readonly string $email,
) {}
```

### 4. Implémenter `applyFilters()` proprement

```php
protected function applyFilters(Builder $query, AbstractRecord $filters): void
{
    // Vérification du type si utilisation d'un Record de filtres dédié
    if (!$filters instanceof UserFiltersRecord) {
        return;
    }

    // Utiliser when() pour les conditions complexes
    $query->when($filters->name ?? null, fn($q, $name) => 
        $q->where('name', 'like', '%' . $name . '%')
    );
    
    $query->when($filters->status ?? null, fn($q, $status) => 
        $q->where('status', $status)
    );
    
    // Filtres cluster
    $query->when($filters->cluster_query ?? null, fn($q, $query) => 
        $q->whereCluster('metadata', $query)
    );
}
```

### 5. Utiliser `whereCluster()` pour les filtres JSON

```php
// ✅ BON - Chaînage pour des requêtes complexes
$users = $repository
    ->whereCluster('metadata', 'status=active')
    ->whereCluster('metadata', 'role=admin')
    ->whereCluster('metadata', 'age>=25')
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// ✅ BON - Avec des filtres Record existants
$filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
$users = $repository
    ->whereCluster('metadata', 'verified=true')
    ->findBy(new FindByRecord(filters: $filters));

// ✅ BON - Nettoyer les filtres entre les requêtes
$repository->clearClusterFilters();
$all = $repository->count();
```

### 6. Utiliser `createRaw` pour des données brutes

```php
// ✅ BON - Quand vous avez déjà des données brutes
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active',
];
$user = $repository->createRaw($data);

// ✅ BON - Pour créer avec des valeurs null explicites
$data = [
    'name' => 'User Without Email',
    'email' => null,
    'status' => 'active',
];
$user = $repository->createRaw($data);
```

### 7. Trier avec SortColumns

```php
// ✅ BON - Tri simple
$sort = new SortColumns('name:asc');

// ✅ BON - Tri multi-colonnes pour des tris complexes
$sort = new SortColumns('category_id:asc|price:desc|id:asc');

// ✅ BON - Format lisible pour les tris multi-colonnes
$sortString = 'status:asc|created_at:desc|name:asc';
$sort = new SortColumns($sortString);
```

### 8. Tester vos Repositories

```php
final class UserRepositoryTest extends IntegrationTestCase
{
    private UserRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }
    
    public function test_create_persiste_utilisateur(): void
    {
        $record = new UserRecord(name: 'John', email: 'john@example.com');
        
        $user = $this->repository->create($record);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);
    }
    
    public function test_where_cluster_filters_results(): void
    {
        $this->createUser(['metadata' => ['status' => 'active', 'role' => 'admin']]);
        $this->createUser(['metadata' => ['status' => 'inactive', 'role' => 'doctor']]);
        
        $users = $this->repository
            ->whereCluster('metadata', 'status=active & role=admin')
            ->findBy(new FindByRecord(filters: new EmptyRecord()));
        
        $this->assertCount(1, $users);
    }
    
    public function test_find_by_with_multiple_sort_columns(): void
    {
        // Créer des utilisateurs
        $this->repository->create(new UserRecord(name: 'User A', email: 'a@test.com'));
        $this->repository->create(new UserRecord(name: 'User A', email: 'b@test.com'));
        $this->repository->create(new UserRecord(name: 'User B', email: 'c@test.com'));
        
        $findBy = new FindByRecord(
            sortBy: new SortColumns('name:asc|id:desc'),
        );
        
        $results = $this->repository->findBy($findBy);
        
        $this->assertSame('User A', $results[0]->name);
        $this->assertSame('User A', $results[1]->name);
        $this->assertSame('User B', $results[2]->name);
    }
}
```

---

## Exemple complet avec filtres complexes et tri multiple

```php
final class OrderRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Order::class, OrderRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (!$filters instanceof OrderFiltersRecord) {
            return;
        }

        // Filtre de plage de dates
        if ($filters->fromDate !== null) {
            $query->whereDate('created_at', '>=', $filters->fromDate);
        }
        
        if ($filters->toDate !== null) {
            $query->whereDate('created_at', '<=', $filters->toDate);
        }

        // Filtre de plage de montants
        if ($filters->minAmount !== null) {
            $query->where('total', '>=', $filters->minAmount);
        }
        
        if ($filters->maxAmount !== null) {
            $query->where('total', '<=', $filters->maxAmount);
        }

        // Filtre de statut
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // Filtre de recherche textuelle
        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('order_number', 'like', '%' . $filters->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $filters->search . '%');
            });
        }
        
        // Filtre cluster sur métadonnées
        if ($filters->cluster_query !== null) {
            $query->whereCluster('metadata', $filters->cluster_query);
        }
    }
}

// Utilisation avec tri multi-colonnes et filtres cluster
$filters = new OrderFiltersRecord(
    fromDate: '2024-01-01',
    toDate: '2024-12-31',
    minAmount: 100,
    status: OrderStatus::PAID,
    search: 'ACME',
    cluster_query: 'priority=high & shipping.express=true',
);

$paginate = new PaginateRecord(
    perPage: 20,
    page: 1,
    sortBy: new SortColumns('status:asc|created_at:desc|total:desc'),
    filters: $filters,
    columns: new SelectColumns(['id', 'order_number', 'total', 'status', 'created_at']),
);

$orders = $repository->paginate($paginate);
```

---

## Support Soft Delete

Le repository détecte automatiquement si votre modèle utilise le trait `SoftDeletes` et adapte son comportement :

| Méthode | Comportement standard | Avec SoftDeletes |
|---------|----------------------|------------------|
| `delete()` | Suppression définitive | Soft delete (`deleted_at` rempli) |
| `find()` | Retourne tous les modèles | Exclut les soft deleted |
| `findWithTrashed()` | Comportement standard | Inclut les soft deleted |
| `restore()` | Non disponible | Restaure un soft deleted |
| `forceDelete()` | Non disponible | Suppression définitive |
| `deleteBulk()` | Suppression définitive groupée | Soft delete groupé |
| `forceDeleteBulk()` | Non disponible | Suppression définitive groupée |

```php
// Modèle avec SoftDelete
final class Product extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['name', 'price', 'quantity', 'metadata'];
    
    protected $casts = [
        'metadata' => 'array',
    ];
}

// Utilisation
$product = $repository->create(new ProductRecord(
    name: 'Laptop', 
    price: 999.99,
    metadata: ClusterVO::fromArray(['category' => 'electronics'])
));

// Soft delete
$repository->delete($product->id);

// Le find normal ne le trouve pas
$found = $repository->find($product->id); // null

// findWithTrashed le trouve
$deleted = $repository->findWithTrashed($product->id); // Product instance

// Restauration
$repository->restore($product->id);

// Suppression définitive (hard delete)
$repository->forceDelete($product->id);

// Nettoyage de masse : supprimer définitivement tous les soft deleted
$filters = new ProductFiltersRecord(includeDeleted: true);
$count = $repository->forceDeleteBulk($filters);
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)