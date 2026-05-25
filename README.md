# Laravel Repository

**A lightweight, type-safe repository pattern implementation for Laravel with Records and Eloquent integration.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%7C11.x%7C12.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Introduction

### Le problème

Les repositories maison dans Laravel souffrent souvent de plusieurs défauts :

| Problème | Conséquence |
|----------|-------------|
| **Code dupliqué** | Les mêmes requêtes `where`, `orderBy`, `paginate` sont répétées |
| **Pas de standardisation** | Chaque développeur écrit ses repositories différemment |
| **Typage faible** | Les entrées/sorties sont souvent des tableaux bruts ou des Models |
| **Pas de cohérence** | `find`, `findOrFail`, `first`, `get` utilisés de manière inconsistante |
| **Tests difficiles** | Pas d'interface standardisée pour le mocking |

### La solution : Laravel Repository

**Laravel Repository** est une implémentation standardisée et type-safe du pattern Repository pour Laravel.

```php
// Définir votre Record
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?UserStatus $status = null,
    ) {}
}

// Définir votre Repository
final class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(User::class, UserRecord::class);
    }

    protected function applyFilters(Builder $query, Recordable $filters): void
    {
        if ($filters->name !== null) {
            $query->where('name', 'like', '%' . $filters->name . '%');
        }
    }
}

// Utilisation
$users = $repository->paginate(new PaginateRecord(perPage: 15));
$user = $repository->create(new UserRecord(name: 'John Doe', email: 'john@example.com'));
$repository->update($user->id, new UserRecord(name: 'Jane Doe'));
```

---

## Installation

```bash
composer require andydefer/laravel-repository
```

Le package s'enregistre automatiquement via Laravel.

### Prérequis

- PHP 8.1 ou supérieur
- Laravel 10.x, 11.x ou 12.x
- Dépendances automatiques :
  - `andydefer/php-records` (structures typées)
  - `laravel/framework`

### Publication de la configuration (optionnel)

```bash
php artisan vendor:publish --tag=repository-config
```

---

## Configuration

```php
// config/repository.php
return [
    // Namespace par défaut pour les repositories
    'namespace' => 'App\\Repositories',

    // Namespace par défaut pour les Records
    'record_namespace' => 'App\\Records',

    // Nombre d'éléments par page par défaut
    'default_per_page' => 15,

    // Nombre maximum d'éléments par page
    'max_per_page' => 100,
];
```

---

## Concepts fondamentaux

### Le Record

Un Record est un DTO typé qui sert d'interface entre votre code et le Repository.

```php
use AndyDefer\Records\AbstractRecord;

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
- ✅ Champs optionnels = `null` par défaut
- ❌ Pas de logique métier
- ❌ Pas de tableau brut (utiliser `TypedCollection`)

### Les Records de configuration

Le package fournit des Records standardisés pour les opérations :

#### FindByRecord

```php
use AndyDefer\Repository\Records\FindByRecord;

$findBy = new FindByRecord(
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    limit: 10,
    sortBy: 'name',
    sortDir: 'asc',
    columns: ['id', 'name', 'email'],
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `filters` | `Recordable` | `EmptyRecord` | Filtres de recherche |
| `limit` | `?int` | `null` | Limite de résultats |
| `sortBy` | `?string` | `null` | Champ de tri |
| `sortDir` | `string` | `'asc'` | Direction du tri |
| `columns` | `array` | `['*']` | Colonnes à sélectionner |

#### PaginateRecord

```php
use AndyDefer\Repository\Records\PaginateRecord;

$paginate = new PaginateRecord(
    perPage: 15,
    page: 1,
    sortBy: 'name',
    sortDir: 'asc',
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    columns: ['id', 'name', 'email'],
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `perPage` | `int` | `15` | Éléments par page |
| `page` | `int` | `1` | Numéro de page |
| `sortBy` | `?string` | `null` | Champ de tri |
| `sortDir` | `string` | `'asc'` | Direction du tri |
| `filters` | `Recordable` | `EmptyRecord` | Filtres de recherche |
| `columns` | `array` | `['*']` | Colonnes à sélectionner |

#### RepositoryInfoRecord

```php
use AndyDefer\Repository\Records\RepositoryInfoRecord;

$info = $repository->info();
// RepositoryInfoRecord {
//     modelClass: 'App\Models\User',
//     recordClass: 'App\Records\UserRecord',
// }
```

---

## Créer votre premier Repository

### 1. Créer le Model

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

use AndyDefer\Records\AbstractRecord;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?UserStatus $status = null,
    ) {}
}
```

### 3. Créer le Record de filtres

```php
<?php

namespace App\Records;

use AndyDefer\Records\AbstractRecord;

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
use AndyDefer\Records\Recordable;
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

    protected function applyFilters(Builder $query, Recordable $filters): void
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
use App\Records\UserStatus;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;

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

    // Mettre à jour
    public function updateUser(int $id, string $name): User
    {
        return $this->repository->update($id, new UserRecord(name: $name));
    }

    // Supprimer
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
        );
        
        return $this->repository->findBy($findBy)->all();
    }

    // Paginer
    public function getPaginatedUsers(int $page = 1): LengthAwarePaginator
    {
        $paginate = new PaginateRecord(
            perPage: 15,
            page: $page,
            sortBy: 'created_at',
            sortDir: 'desc',
        );
        
        return $this->repository->paginate($paginate);
    }

    // Compter
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

## API Reference

### AbstractRepository

| Méthode | Paramètres | Retour | Description |
|---------|------------|--------|-------------|
| `info()` | - | `RepositoryInfoRecord` | Informations sur le repository |
| `create(Recordable $record)` | `$record` | `Model` | Crée un nouvel enregistrement |
| `find(int $id)` | `$id` | `Model|null` | Trouve par ID |
| `findBy(FindByRecord $record)` | `$record` | `Collection<Model>` | Recherche avec critères |
| `update(int $id, Recordable $record)` | `$id, $record` | `Model` | Met à jour (champs non-null seulement) |
| `delete(int $id)` | `$id` | `bool` | Supprime par ID |
| `count(?Recordable $criteria)` | `$criteria` | `int` | Compte les enregistrements |
| `exists(Recordable $criteria)` | `$criteria` | `bool` | Vérifie l'existence |
| `paginate(PaginateRecord $record)` | `$record` | `LengthAwarePaginator` | Résultats paginés |
| `deleteBulk(Recordable $criteria)` | `$criteria` | `int` | Suppression groupée |

### Méthodes à surcharger

| Méthode | Description |
|---------|-------------|
| `applyFilters(Builder $query, Recordable $filters)` | Applique les filtres de recherche (à surcharger) |

### Exceptions

| Exception | Quand |
|-----------|-------|
| `ModelNotFoundException` | `update()` sur un ID inexistant |

---

## Bonnes pratiques

### 1. Un Record par entité

```php
// ✅ BON
final class UserRecord extends AbstractRecord { ... }
final class PostRecord extends AbstractRecord { ... }

// ❌ MAUVAIS
final class UserPostRecord extends AbstractRecord { ... }
```

### 2. Record de filtres séparé

```php
// ✅ BON
final class UserFiltersRecord extends AbstractRecord { ... }

// ❌ MAUVAIS - Réutiliser le Record principal
final class UserRecord extends AbstractRecord
{
    // Mélange création et filtrage
}
```

### 3. Utiliser les valeurs par défaut pour les champs optionnels

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
protected function applyFilters(Builder $query, Recordable $filters): void
{
    if (!$filters instanceof UserFiltersRecord) {
        return;
    }

    // Utiliser `when()` pour des conditions complexes
    $query->when($filters->name, fn($q, $name) => 
        $q->where('name', 'like', '%' . $name . '%')
    );
    
    $query->when($filters->status, fn($q, $status) => 
        $q->where('status', $status)
    );
}
```

### 5. Tester vos repositories

```php
final class UserRepositoryTest extends IntegrationTestCase
{
    private UserRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }
    
    public function test_create_persists_user(): void
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

## Exemple complet avec filtres complexes

```php
final class OrderRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Order::class, OrderRecord::class);
    }

    protected function applyFilters(Builder $query, Recordable $filters): void
    {
        if (!$filters instanceof OrderFiltersRecord) {
            return;
        }

        // Filtre par date
        if ($filters->fromDate !== null) {
            $query->whereDate('created_at', '>=', $filters->fromDate);
        }
        
        if ($filters->toDate !== null) {
            $query->whereDate('created_at', '<=', $filters->toDate);
        }

        // Filtre par montant
        if ($filters->minAmount !== null) {
            $query->where('total', '>=', $filters->minAmount);
        }
        
        if ($filters->maxAmount !== null) {
            $query->where('total', '<=', $filters->maxAmount);
        }

        // Filtre par statut
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // Filtre par recherche
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
    sortDir: 'desc',
    filters: $filters,
);

$orders = $repository->paginate($paginate);
```

---

## Questions fréquentes

### Q: Pourquoi ne pas utiliser directement les Models ?

**R:** Les repositories offrent une couche d'abstraction qui :
- Centralise la logique d'accès aux données
- Facilite le mocking dans les tests
- Permet de changer d'implémentation (ex: passer d'Eloquent à Redis)

### Q: Quelle est la différence entre `Record` et `Data` ?

**R:** 
- `Record` : Communication interne (Services, Repositories)
- `Data` : Réponses API (Actions)

### Q: Puis-je utiliser `array` au lieu de `TypedCollection` ?

**R:** Non. Les tableaux bruts sont interdits dans les Records. Utilisez `TypedCollection` pour garantir la sécurité des types.

### Q: Comment gérer les relations ?

**R:** Les relations sont gérées dans le Repository :

```php
public function getUserWithPosts(int $userId): ?User
{
    return $this->model->newQuery()
        ->with('posts')
        ->find($userId);
}
```

### Q: Puis-je utiliser ce package sans Laravel ?

**R:** Non, le package dépend de Laravel (Eloquent, migrations, configuration).

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)