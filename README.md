# Laravel Repository

**Une implémentation légère et typée du pattern Repository pour Laravel avec intégration Records et Eloquent.**

[![Version PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Version Laravel](https://img.shields.io/badge/Laravel-12.x%20|%2013.x%20|%2014.x%20|%2015.x-blue)](https://laravel.com)
[![Licence](https://img.shields.io/badge/Licence-MIT-green)](LICENSE)

---

## Installation

```bash
composer require andydefer/laravel-repository
```

### Prérequis

- PHP 8.1 ou supérieur
- Laravel 12.x, 13.x, 14.x ou 15.x
- Dépendances automatiques :
  - `andydefer/php-records` (structures typées)
  - `laravel/framework`

### Publier la configuration (Optionnel)

```bash
php artisan vendor:publish --tag=repository-config
```

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

### Records de configuration

Le package fournit des Records standardisés pour les opérations :

#### FindByRecord

```php
use AndyDefer\Repository\Records\FindByRecord;

$findBy = new FindByRecord(
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    limit: 10,
    sortBy: 'name',
    sortDir: SortDirection::ASC,
    columns: new SelectColumns(['id', 'name', 'email']),
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `filters` | `AbstractRecord` | `EmptyRecord` | Filtres de recherche |
| `limit` | `?int` | `null` | Limite de résultats |
| `sortBy` | `?string` | `null` | Champ de tri |
| `sortDir` | `SortDirection` | `SortDirection::ASC` | Direction du tri |
| `columns` | `SelectColumns` | `SelectColumns::all()` | Colonnes à sélectionner |

#### PaginateRecord

```php
use AndyDefer\Repository\Records\PaginateRecord;

$paginate = new PaginateRecord(
    perPage: 15,
    page: 1,
    sortBy: 'name',
    sortDir: SortDirection::ASC,
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    columns: new SelectColumns(['id', 'name', 'email']),
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `perPage` | `int` | `15` | Éléments par page |
| `page` | `int` | `1` | Numéro de page |
| `sortBy` | `?string` | `null` | Champ de tri |
| `sortDir` | `SortDirection` | `SortDirection::ASC` | Direction du tri |
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

### Énumération SortDirection

```php
use AndyDefer\Repository\Enums\SortDirection;

$direction = SortDirection::ASC;

$direction->isAsc();     // true
$direction->isDesc();    // false
$direction->opposite();  // SortDirection::DESC
$direction->toSql();     // 'asc'
```

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
    protected $fillable = ['name', 'email', 'status'];
}
```

### 2. Créer le Record

```php
<?php

namespace App\Records;

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
use AndyDefer\Repository\Enums\SortDirection;

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

    // Lister avec filtres
    public function listActiveUsers(): array
    {
        $filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
        $findBy = new FindByRecord(
            filters: $filters,
            limit: 50,
            sortBy: 'name',
            sortDir: SortDirection::ASC,
        );
        
        return $this->repository->findBy($findBy)->all();
    }

    // Paginer les résultats
    public function getPaginatedUsers(int $page = 1): LengthAwarePaginator
    {
        $paginate = new PaginateRecord(
            perPage: 15,
            page: $page,
            sortBy: 'created_at',
            sortDir: SortDirection::DESC,
        );
        
        return $this->repository->paginate($paginate);
    }

    // Compter les enregistrements
    public function countActiveUsers(): int
    {
        $filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
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
}
```

---

## Référence de l'API

### AbstractRepository

| Méthode | Paramètres | Retour | Description |
|---------|------------|--------|-------------|
| `info()` | - | `RepositoryInfoRecord` | Informations du repository |
| `create(AbstractRecord $record)` | `$record` | `Model` | Créer un nouvel enregistrement |
| `find(int $id)` | `$id` | `Model|null` | Trouver par ID |
| `findBy(FindByRecord $record)` | `$record` | `Collection<Model>` | Rechercher avec critères |
| `update(int $id, AbstractRecord $record)` | `$id, $record` | `Model` | Mettre à jour (champs non-nuls uniquement) |
| `delete(int $id)` | `$id` | `bool` | Supprimer par ID |
| `count(?AbstractRecord $criteria)` | `$criteria` | `int` | Compter les enregistrements |
| `exists(AbstractRecord $criteria)` | `$criteria` | `bool` | Vérifier l'existence |
| `paginate(PaginateRecord $record)` | `$record` | `LengthAwarePaginator` | Résultats paginés |
| `deleteBulk(AbstractRecord $criteria)` | `$criteria` | `int` | Suppression groupée |

### Méthodes à surcharger

| Méthode | Description |
|---------|-------------|
| `applyFilters(Builder $query, AbstractRecord $filters)` | Appliquer les filtres de recherche (doit être surchargée) |

### Exceptions

| Exception | Quand |
|-----------|-------|
| `ModelNotFoundException` | `update()` sur un ID inexistant |
| `InvalidArgumentException` | Nom de colonne invalide dans `SelectColumns` |

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
}
```

### 5. Tester vos Repositories

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
}
```

---

## Exemple complet avec filtres complexes

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
    }
}

// Utilisation
$filters = new OrderFiltersRecord(
    fromDate: '2024-01-01',
    toDate: '2024-12-31',
    minAmount: 100,
    status: OrderStatus::PAID,
    search: 'ACME',
);

$paginate = new PaginateRecord(
    perPage: 20,
    page: 1,
    sortBy: 'created_at',
    sortDir: SortDirection::DESC,
    filters: $filters,
    columns: new SelectColumns(['id', 'order_number', 'total', 'status']),
);

$orders = $repository->paginate($paginate);
```

---

## Tests

### Configuration des tests

Le package utilise SQLite en mémoire pour les tests d'intégration :

```php
// tests/IntegrationTestCase.php
protected function defineEnvironment($app): void
{
    $app['config']->set('database.default', 'testbench');
    $app['config']->set('database.connections.testbench', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
}
```

### Exécuter les tests

```bash
composer test
```

---

## Génération de code avec Directive Forge

Ce package s'intègre à `directive-forge` pour générer automatiquement les repositories, records et filtres.

### Installer Directive Forge

```bash
composer require andydefer/directive-forge --dev
```

### Commandes disponibles

```bash
# Générer un repository
./vendor/bin/directive make-repository user

# Générer un record
./vendor/bin/directive make-record user-data

# Générer un record de filtres
./vendor/bin/directive make-filters-record user-filters
```

### Exemple de génération

```bash
# Créer un repository User
./vendor/bin/directive make-repository user

# Génère :
# - app/Repositories/UserRepository.php
# - app/Records/UserRecord.php (optionnel)
# - app/Records/UserFiltersRecord.php (optionnel)
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)