# Laravel Repository

**Une implémentation légère et typée du pattern Repository pour Laravel avec intégration Records, Eloquent et Enum Casting.**

[![Version PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Version Laravel](https://img.shields.io/badge/Laravel-12.x%20|%2013.x%20|%2014.x%20|%2015.x-blue)](https://laravel.com)
[![Licence](https://img.shields.io/badge/Licence-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Concepts fondamentaux](#concepts-fondamentaux)
3. [Créer votre premier Repository](#créer-votre-premier-repository)
4. [Enum Cast - Cast automatique des enums](#enum-cast---cast-automatique-des-enums)
5. [Référence de l'API](#référence-de-lapi)
6. [Méthodes à surcharger](#méthodes-à-surcharger)
7. [Proxies - AttributeProxy et TransformableProxy](#proxies---attributeproxy-et-transformableproxy)
8. [Bonnes pratiques](#bonnes-pratiques)
9. [Exemple complet avec filtres complexes](#exemple-complet-avec-filtres-complexes)
10. [Licence](#licence)

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

### Multiples colonnes JSON

Appliquez des filtres sur plusieurs colonnes JSON en une seule fois :

```php
// Avec whereClusters()
$users = $repository
    ->whereClusters([
        'metadata' => 'status=active & role=admin',
        'preferences' => 'color=blue & size=large',
    ])
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// Avec ClusterQueries dans FindByRecord
use AndyDefer\Repository\ValueObjects\ClusterQueries;

$queries = new ClusterQueries([
    'metadata' => 'status=active',
    'preferences' => 'color=blue',
]);

$findBy = new FindByRecord(
    filters: new EmptyRecord,
    clusterQueries: $queries,
);

$users = $repository->findBy($findBy);
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

// Avec ClusterQueries
use AndyDefer\Repository\ValueObjects\ClusterQueries;

$queries = new ClusterQueries([
    'metadata' => 'status=active',
    'preferences' => 'color=blue',
]);

$findBy = new FindByRecord(
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    limit: 10,
    sortBy: new SortColumns('name:asc|created_at:desc'),
    columns: new SelectColumns(['id', 'name', 'email']),
    clusterQueries: $queries,
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `filters` | `AbstractRecord` | `EmptyRecord` | Filtres de recherche |
| `limit` | `?int` | `null` | Limite de résultats |
| `sortBy` | `?SortColumns` | `null` | Colonnes de tri (supporte le multi-colonnes) |
| `columns` | `SelectColumns` | `SelectColumns::all()` | Colonnes à sélectionner |
| `clusterQueries` | `?ClusterQueries` | `null` | Requêtes cluster sur colonnes JSON |

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

// Avec ClusterQueries
$queries = new ClusterQueries([
    'metadata' => 'status=active',
    'preferences' => 'color=blue',
]);

$paginate = new PaginateRecord(
    perPage: 15,
    page: 1,
    sortBy: new SortColumns('created_at:desc'),
    filters: new UserFiltersRecord(status: UserStatus::ACTIVE),
    columns: new SelectColumns(['id', 'name', 'email']),
    clusterQueries: $queries,
);
```

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `perPage` | `int` | `15` | Éléments par page |
| `page` | `int` | `1` | Numéro de page |
| `sortBy` | `?SortColumns` | `null` | Colonnes de tri (supporte le multi-colonnes) |
| `filters` | `AbstractRecord` | `EmptyRecord` | Filtres de recherche |
| `columns` | `SelectColumns` | `SelectColumns::all()` | Colonnes à sélectionner |
| `clusterQueries` | `?ClusterQueries` | `null` | Requêtes cluster sur colonnes JSON |

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

### Objet Valeur ClusterQueries

```php
use AndyDefer\Repository\ValueObjects\ClusterQueries;

// Créer avec plusieurs colonnes
$queries = new ClusterQueries([
    'metadata' => 'status=active & role=admin',
    'preferences' => 'color=blue & size=large',
    'settings' => 'theme=dark',
]);

// Vérifier si une colonne a une requête
if ($queries->has('metadata')) {
    $query = $queries->get('metadata'); // 'status=active & role=admin'
}

// Obtenir toutes les requêtes
$allQueries = $queries->all(); // ['metadata' => '...', 'preferences' => '...']

// Compter les requêtes
$count = $queries->count(); // 3

// Vérifier si vide
if ($queries->isEmpty()) {
    // Aucune requête
}

// Fusionner deux ensembles de requêtes
$otherQueries = new ClusterQueries([
    'settings' => 'language=fr',
]);
$merged = $queries->merge($otherQueries);
// $merged contient metadata, preferences, settings avec 'language=fr'
```

---

## Enum Cast - Cast automatique des enums

Le package fournit un cast Eloquent générique `EnumCast` qui permet de mapper automatiquement des colonnes de base de données vers des enums personnalisés.

### Configuration

```php
// config/repository.php

return [
    /*
    |--------------------------------------------------------------------------
    | Enum Casts
    |--------------------------------------------------------------------------
    |
    | Define enum casts for specific tables and columns.
    | Each entry maps a table name and column to an enum class.
    |
    | The enum class must implement EnumerableInterface.
    |
    | Example:
    | 'enum_casts' => [
    |     'likes' => [
    |         'type' => AndyDefer\LaravelLikes\Enums\LikeType::class,
    |     ],
    |     'signalements' => [
    |         'type' => App\Enums\SignalementType::class,
    |         'status' => App\Enums\SignalementStatus::class,
    |     ],
    | ],
    |
    */
    'enum_casts' => [],
];
```

### Interface EnumerableInterface

Tous les enums utilisés avec `EnumCast` doivent implémenter `EnumerableInterface` :

```php
use AndyDefer\Repository\Contracts\EnumerableInterface;

enum ProductStatus: string implements EnumerableInterface
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
    case OUT_OF_STOCK = 'out_of_stock';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PUBLISHED => 'Publié',
            self::ARCHIVED => 'Archivé',
            self::OUT_OF_STOCK => 'Rupture de stock',
        };
    }
}
```

### Utilisation dans le modèle

```php
use AndyDefer\Repository\Casts\EnumCast;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $casts = [
        'status' => EnumCast::class,
    ];
}
```

### Exemple complet

```php
// 1. Configurer
// config/repository.php
'enum_casts' => [
    'products' => [
        'status' => ProductStatus::class,
    ],
],

// 2. Créer l'enum
enum ProductStatus: string implements EnumerableInterface
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PUBLISHED => 'Publié',
            self::ARCHIVED => 'Archivé',
        };
    }
}

// 3. Modèle
class Product extends Model
{
    protected $casts = [
        'status' => EnumCast::class,
    ];
}

// 4. Utilisation
$product = Product::create([
    'name' => 'Laptop',
    'status' => 'published',
]);

echo $product->status->getLabel(); // 'Publié'

$product->status = ProductStatus::ARCHIVED;
$product->save();

echo $product->status->getValue(); // 'archived'
```

### Fonctionnement du cast

| Opération | Comportement |
|-----------|--------------|
| **Lecture (get)** | Convertit la valeur string/int de la base de données en instance de l'enum configuré via `tryFrom()` |
| **Écriture (set)** | Accepte une instance de `EnumerableInterface` ou une string/int et la convertit en valeur de base de données |

---

## Créer votre premier Repository

### 1. Créer le Modèle

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $fillable = ['name', 'email', 'status', 'metadata', 'preferences'];
    
    protected $casts = [
        'metadata' => 'array',
        'preferences' => 'array',
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
        public readonly ?ClusterVO $preferences = null,
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
use AndyDefer\Repository\ValueObjects\ClusterQueries;

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
        $queries = new ClusterQueries([
            'metadata' => 'status=active & role=admin',
            'preferences' => 'theme=dark & notifications=true',
        ]);
        
        $filters = new UserFiltersRecord(status: UserStatus::ACTIVE);
        
        $findBy = new FindByRecord(
            filters: $filters,
            limit: 50,
            sortBy: new SortColumns('status:asc|name:asc'),
            clusterQueries: $queries,
        );
        
        return $this->repository->findBy($findBy)->all();
    }

    // Paginer les résultats avec tri multiple et filtres cluster
    public function getPaginatedUsers(int $page = 1): LengthAwarePaginator
    {
        $queries = new ClusterQueries([
            'metadata' => 'status=active',
            'preferences' => 'theme=dark',
        ]);
        
        $paginate = new PaginateRecord(
            perPage: 15,
            page: $page,
            sortBy: new SortColumns('created_at:desc|id:asc'),
            clusterQueries: $queries,
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

    // Suppression groupée avec filtres cluster
    public function deleteInactiveUsers(): int
    {
        return $this->repository
            ->whereCluster('metadata', 'status=inactive')
            ->deleteBulk(new EmptyRecord());
    }
    
    // Suppression définitive groupée
    public function forceDeleteInactiveUsers(): int
    {
        return $this->repository
            ->whereClusters([
                'metadata' => 'status=inactive',
                'preferences' => 'notifications=false',
            ])
            ->forceDeleteBulk(new EmptyRecord());
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
| `whereClusters(array $queries)` | `$queries` | `$this` | Appliquer plusieurs filtres cluster |
| `whereClusterQueries(ClusterQueries $queries)` | `$queries` | `$this` | Appliquer des filtres depuis un VO |
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
| `InvalidArgumentException` | Valeur enum non valide dans `EnumCast` |

---

## Proxies - AttributeProxy et TransformableProxy

### Qu'est-ce que c'est ?

Les Proxies sont des classes qui permettent de **caster automatiquement** les attributs Eloquent vers des Value Objects, Records ou Collections typées de manière transparente.

### Pourquoi les utiliser ?

| Sans Proxies | Avec Proxies |
|--------------|--------------|
| Cast manuel dans `$casts` | Cast automatique via `AttributeProxy` |
| Logique de transformation répétée | Logique centralisée |
| Code boilerplate | Code propre et lisible |
| Pas de typage fort | Typage fort (VO, Record, Collection) |

### TransformableProxy

`TransformableProxy` est le cœur du système. Il hydrate un objet `Transformable` depuis n'importe quelle source (string, array, JSON).

```php
use AndyDefer\Repository\Proxies\TransformableProxy;

// Depuis une string
$slug = TransformableProxy::make(SlugVO::class, 'my-article');

// Depuis un tableau
$user = TransformableProxy::make(UserRecord::class, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Depuis du JSON
$coordinates = TransformableProxy::make(
    CoordinatesVO::class,
    '{"latitude":48.8566,"longitude":2.3522}'
);
```

**Signature :**
```php
public static function make(
    string $class,   // class-string<T> - La classe cible (doit implémenter Transformable)
    mixed $value,    // La source de données (string, array, JSON, etc.)
    bool $nullable = false // Si true, retourne null quand $value est null
): mixed
```

### AttributeProxy

`AttributeProxy` est un helper pour créer des attributs Eloquent typés. Il utilise `TransformableProxy` en interne.

```php
use AndyDefer\Repository\Proxies\AttributeProxy;
use Illuminate\Database\Eloquent\Casts\Attribute;

// Attribut nullable (retourne null si la colonne est null)
protected function slug(): Attribute
{
    return AttributeProxy::nullable(SlugVO::class, column: 'slug');
}

// Attribut required (lance une exception si la colonne est null)
protected function coordinates(): Attribute
{
    return AttributeProxy::required(CoordinatesVO::class, column: 'coordinates');
}
```

**Méthodes disponibles :**

| Méthode | Description | Utilisation |
|---------|-------------|-------------|
| `required(string $class, ?string $column = null)` | Attribut requis | `AttributeProxy::required(SlugVO::class, column: 'slug')` |
| `nullable(string $class, ?string $column = null)` | Attribut nullable | `AttributeProxy::nullable(SlugVO::class, column: 'slug')` |
| `make(string $class, bool $nullable = false, ?string $column = null)` | Déprécié | Utiliser `required()` ou `nullable()` à la place |

### Exemple complet d'utilisation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use AndyDefer\PhpVo\ValueObjects\SlugVO;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\Repository\Proxies\AttributeProxy;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\Repository\Tests\Fixtures\Collections\TestLanguageCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

final class Article extends Model
{
    protected $table = 'articles';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'coordinates',
        'metadata',
        'languages',
    ];

    protected $casts = [
        'metadata' => 'array',
        'languages' => 'array',
    ];

    /**
     * Cast le slug en SlugVO.
     */
    protected function slug(): Attribute
    {
        return AttributeProxy::nullable(SlugVO::class, column: 'slug');
    }

    /**
     * Cast les coordonnées en CoordinatesVO.
     */
    protected function coordinates(): Attribute
    {
        return AttributeProxy::required(CoordinatesVO::class, column: 'coordinates');
    }

    /**
     * Cast les métadonnées en UserRecord.
     */
    protected function userRecord(): Attribute
    {
        return AttributeProxy::nullable(TestUserRecord::class, column: 'metadata');
    }

    /**
     * Cast les langues en LanguageCollection.
     */
    protected function languages(): Attribute
    {
        return AttributeProxy::nullable(TestLanguageCollection::class, column: 'languages');
    }
}

// Utilisation
$article = Article::create([
    'title' => 'Mon article',
    'slug' => 'mon-article',
    'coordinates' => ['latitude' => 48.8566, 'longitude' => 2.3522],
    'languages' => ['fr', 'en'],
]);

// Les attributs sont automatiquement hydratés
echo $article->slug->getValue();        // 'mon-article'
echo $article->coordinates->getLatitude(); // 48.8566
echo $article->languages->count();      // 2

// Mise à jour avec des objets
$article->slug = new SlugVO('nouveau-slug');
$article->save();
```

### Classes compatibles

`AttributeProxy` fonctionne avec :

| Type | Exemple | Condition |
|------|---------|-----------|
| **Value Object** | `SlugVO`, `EmailVO`, `Money` | Doit implémenter `Transformable` |
| **Record** | `UserRecord`, `OrderRecord` | Étend `AbstractRecord` (qui implémente `Transformable`) |
| **Collection** | `LanguageCollection` | Étend `TypedCollection` et implémente `Transformable` |
| **Enum** | `UserStatus`, `UserRole` | Enum PHP (n'implémente pas `Transformable`) |

### Bonnes pratiques avec les Proxies

#### 1. Utiliser `required()` ou `nullable()` selon le besoin

```php
// ✅ BON - Attribut requis
protected function slug(): Attribute
{
    return AttributeProxy::required(SlugVO::class, column: 'slug');
}

// ✅ BON - Attribut nullable
protected function slug(): Attribute
{
    return AttributeProxy::nullable(SlugVO::class, column: 'slug');
}
```

#### 2. Spécifier le nom de colonne quand il est différent

```php
// ✅ BON - Colonne explicite
protected function userRecord(): Attribute
{
    return AttributeProxy::nullable(UserRecord::class, column: 'metadata');
}

// ✅ BON - Colonne avec le même nom que la méthode
protected function slug(): Attribute
{
    return AttributeProxy::nullable(SlugVO::class, column: 'slug');
}
```

#### 3. Ne pas mélanger avec `$casts`

```php
// ✅ BON - Un attribut dans le cast ou via AttributeProxy, pas les deux
protected $casts = [
    'metadata' => 'array',  // Garder pour les données brutes
    'languages' => 'array',
];

protected function userRecord(): Attribute
{
    // Utilise le cast 'array' pour récupérer les données brutes
    return AttributeProxy::nullable(UserRecord::class, column: 'metadata');
}

// ❌ MAUVAIS - Doublon de cast
protected $casts = [
    'metadata' => 'array',  // Déjà casté
];

protected function metadata(): Attribute
{
    // Un autre cast sur la même colonne → conflit !
    return AttributeProxy::nullable(UserRecord::class, column: 'metadata');
}
```

#### 4. Toujours utiliser `AttributeProxy` dans les modèles

```php
// ✅ BON - Utiliser AttributeProxy
protected function slug(): Attribute
{
    return AttributeProxy::nullable(SlugVO::class, column: 'slug');
}

// ❌ MAUVAIS - Logique manuelle dans l'attribut
protected function slug(): Attribute
{
    return Attribute::make(
        get: fn ($value) => $value ? new SlugVO($value) : null,
        set: fn ($value) => $value instanceof SlugVO ? $value->getValue() : $value,
    );
}
```

#### 5. Vérifier les types dans les tests

```php
public function test_slug_attribute_returns_vo(): void
{
    $user = TestUser::create(['slug' => 'john-doe']);
    
    $this->assertInstanceOf(SlugVO::class, $user->slug);
    $this->assertSame('john-doe', $user->slug->getValue());
}

public function test_nullable_attribute_returns_null(): void
{
    $user = TestUser::create(['slug' => null]);
    
    $this->assertNull($user->slug);
}
```

### Erreurs fréquentes avec les Proxies

#### Erreur 1 : Classe non Transformable

❌ **Mauvais**
```php
protected function custom(): Attribute
{
    return AttributeProxy::nullable(\stdClass::class, column: 'data');
}
// InvalidArgumentException: Class stdClass must implement Transformable interface
```

✅ **Bon**
```php
protected function custom(): Attribute
{
    return AttributeProxy::nullable(MyRecord::class, column: 'data');
}
```

#### Erreur 2 : Conflit avec $casts

❌ **Mauvais**
```php
protected $casts = [
    'metadata' => ClusterCast::class,
];

protected function userRecord(): Attribute
{
    return AttributeProxy::nullable(UserRecord::class, column: 'metadata');
}
// Conflit : metadata est déjà casté en ClusterVO
```

✅ **Bon**
```php
protected $casts = [
    'metadata' => 'array',  // ← Garder brut
];

protected function userRecord(): Attribute
{
    return AttributeProxy::nullable(UserRecord::class, column: 'metadata');
}
```

#### Erreur 3 : Valeur null avec required()

❌ **Mauvais**
```php
protected function coordinates(): Attribute
{
    return AttributeProxy::required(CoordinatesVO::class, column: 'coordinates');
}

$model = TestModel::create(['coordinates' => null]);
// InvalidArgumentException: Value cannot be null
```

✅ **Bon**
```php
protected function coordinates(): Attribute
{
    return AttributeProxy::nullable(CoordinatesVO::class, column: 'coordinates');
}
```

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
    if (!$filters instanceof UserFiltersRecord) {
        return;
    }

    $query->when($filters->name ?? null, fn($q, $name) => 
        $q->where('name', 'like', '%' . $name . '%')
    );
    
    $query->when($filters->status ?? null, fn($q, $status) => 
        $q->where('status', $status)
    );
    
    $query->when($filters->cluster_query ?? null, fn($q, $query) => 
        $q->whereCluster('metadata', $query)
    );
}
```

### 5. Utiliser `whereClusters()` pour plusieurs colonnes

```php
// ✅ BON - Différentes colonnes
$users = $repository
    ->whereClusters([
        'metadata' => 'status=active',
        'preferences' => 'color=blue',
        'settings' => 'theme=dark',
    ])
    ->findBy(new FindByRecord(filters: new EmptyRecord()));

// ✅ BON - Combiner avec whereCluster() chaîné
$users = $repository
    ->whereCluster('metadata', 'status=active')
    ->whereCluster('metadata', 'role=admin')
    ->whereClusters([
        'preferences' => 'color=blue',
        'settings' => 'theme=dark',
    ])
    ->findBy(new FindByRecord(filters: new EmptyRecord()));
```

### 6. Utiliser `ClusterQueries` dans les Records

```php
// ✅ BON - Réutilisable et testable
$queries = new ClusterQueries([
    'metadata' => 'status=active',
    'preferences' => 'color=blue',
]);

$findBy = new FindByRecord(
    filters: $filters,
    limit: 10,
    sortBy: new SortColumns('name:asc'),
    clusterQueries: $queries,
);

$users = $repository->findBy($findBy);
```

### 7. Nettoyer les filtres cluster

```php
// ✅ BON - Nettoyer entre les requêtes
$repository
    ->whereCluster('metadata', 'status=active')
    ->count(); // 5

$repository->clearClusterFilters();
$all = $repository->count(); // 10

// ✅ BON - ou créer une nouvelle instance
$repository = new UserRepository();
$activeCount = $repository
    ->whereCluster('metadata', 'status=active')
    ->count();
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
    
    public function test_where_clusters_on_multiple_columns(): void
    {
        $this->createUser([
            'metadata' => ['status' => 'active', 'role' => 'admin'],
            'preferences' => ['color' => 'blue', 'size' => 'large'],
        ]);
        
        $this->createUser([
            'metadata' => ['status' => 'active', 'role' => 'doctor'],
            'preferences' => ['color' => 'red', 'size' => 'large'],
        ]);
        
        $users = $this->repository
            ->whereClusters([
                'metadata' => 'status=active & role=admin',
                'preferences' => 'color=blue',
            ])
            ->findBy(new FindByRecord(filters: new EmptyRecord()));
        
        $this->assertCount(1, $users);
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

        if ($filters->fromDate !== null) {
            $query->whereDate('created_at', '>=', $filters->fromDate);
        }
        
        if ($filters->toDate !== null) {
            $query->whereDate('created_at', '<=', $filters->toDate);
        }

        if ($filters->minAmount !== null) {
            $query->where('total', '>=', $filters->minAmount);
        }
        
        if ($filters->maxAmount !== null) {
            $query->where('total', '<=', $filters->maxAmount);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('order_number', 'like', '%' . $filters->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $filters->search . '%');
            });
        }
        
        if ($filters->cluster_query !== null) {
            $query->whereCluster('metadata', $filters->cluster_query);
        }
    }
}

// Utilisation avec ClusterQueries, tri multi-colonnes et filtres
use AndyDefer\Repository\ValueObjects\ClusterQueries;

$clusterQueries = new ClusterQueries([
    'metadata' => 'status=active & priority=high',
    'preferences' => 'shipping.express=true',
]);

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
    sortBy: new SortColumns('status:asc|created_at:desc|total:desc'),
    filters: $filters,
    columns: new SelectColumns(['id', 'order_number', 'total', 'status', 'created_at']),
    clusterQueries: $clusterQueries,
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
    
    protected $fillable = ['name', 'price', 'quantity', 'metadata', 'preferences'];
    
    protected $casts = [
        'metadata' => 'array',
        'preferences' => 'array',
    ];
}

// Utilisation
$product = $repository->create(new ProductRecord(
    name: 'Laptop', 
    price: 999.99,
    metadata: ClusterVO::fromArray(['category' => 'electronics']),
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

// Nettoyage de masse avec filtres cluster
$deletedCount = $repository
    ->whereCluster('metadata', 'category=discontinued')
    ->forceDeleteBulk(new ProductFiltersRecord(includeDeleted: true));
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
