# EnumCast - Référence Technique

## Description

Convertit automatiquement les colonnes de base de données en instances d'enum (et inversement) en utilisant la configuration du repository pour déterminer la classe enum à utiliser par table et colonne.

## Hiérarchie

```
CastsAttributes<EnumerableInterface, string|int>
    └── EnumCast
```

## Rôle principal

`EnumCast` est un cast Eloquent générique qui permet de mapper automatiquement des colonnes de base de données vers des enums personnalisés. Il s'appuie sur la configuration du package Repository pour déterminer quelle classe enum utiliser pour chaque table et colonne, offrant ainsi une flexibilité totale sans avoir à créer des casts dédiés pour chaque enum.

## API / Méthodes publiques

### `get($model, string $key, $value, array $attributes): ?EnumerableInterface`

Transforme la valeur de la base de données en instance de `EnumerableInterface`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model` | L'instance du modèle Eloquent |
| `$key` | `string` | Le nom de l'attribut |
| `$value` | `string\|int\|null` | La valeur brute de la base de données |
| `$attributes` | `array<string, mixed>` | Tous les attributs du modèle |

**Retourne :** `EnumerableInterface|null` - L'instance de l'enum correspondante, ou `null` si la valeur est `null`

**Exceptions :** Aucune (les erreurs sont capturées et retournent `null`)

**Exemple :**
```php
$product = TestProduct::find(1);
$status = $product->status; // TestProductStatus::PUBLISHED
echo $status->getValue(); // 'published'
echo $status->getLabel(); // 'Publié'
```

---

### `set($model, string $key, $value, array $attributes): string|int|null`

Transforme la valeur de l'enum en valeur de base de données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model` | L'instance du modèle Eloquent |
| `$key` | `string` | Le nom de l'attribut |
| `$value` | `EnumerableInterface\|string\|int\|null` | La valeur à stocker |
| `$attributes` | `array<string, mixed>` | Tous les attributs du modèle |

**Retourne :** `string|int|null` - La valeur à stocker en base de données

**Exceptions :** `InvalidArgumentException` si la valeur n'est pas valide

**Exemple :**
```php
$product = TestProduct::find(1);
$product->status = TestProductStatus::ARCHIVED; // Converti en 'archived'
$product->save();
```

## Flux d'exécution

### Lecture (get)
```
$value → $table = $model->getTable()
    ↓
$enumClass = $this->config->getEnumCast($table, $key)
    ↓
$enumClass::tryFrom($value)
    ↓
? EnumerableInterface : null
```

### Écriture (set)
```
$value
    ↓
$value instanceof EnumerableInterface ?
    ├── OUI → $value->getValue()
    └── NON → is_string($value) ou is_int($value) ?
        ├── OUI → $enumClass::tryFrom($value)
        │   ├── instanceof EnumerableInterface → $enum->getValue()
        │   ├── property_exists('value') → $enum->value
        │   └── default → $enum->name
        └── NON → InvalidArgumentException
```

## Cas d'utilisation

### Cas 1 : Statut d'un produit avec enum personnalisé

**Problème :** Gérer les statuts d'un produit (brouillon, publié, archivé, rupture de stock) avec un enum typé.

**Solution :** Utiliser `EnumCast` avec la configuration appropriée.

```php
// 1. Créer l'enum
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

// 2. Configurer le cast
// config/repository.php
'enum_casts' => [
    'products' => [
        'status' => ProductStatus::class,
    ],
],

// 3. Utiliser dans le modèle
class Product extends Model
{
    protected $casts = [
        'status' => EnumCast::class,
    ];
}

// 4. Utilisation
$product = Product::create([
    'name' => 'Laptop',
    'status' => ProductStatus::PUBLISHED,
]);

$status = $product->status; // ProductStatus::PUBLISHED
echo $status->getLabel(); // 'Publié'
```

### Cas 2 : Types de signalements avec enum personnalisé

**Problème :** Gérer différents types de signalements (spam, harcèlement, contenu inapproprié).

**Solution :** Utiliser `EnumCast` pour mapper automatiquement la colonne `type`.

```php
// 1. Créer l'enum
enum ReportType: string implements EnumerableInterface
{
    case SPAM = 'spam';
    case HARASSMENT = 'harassment';
    case INAPPROPRIATE = 'inappropriate';
    case COPYRIGHT = 'copyright';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SPAM => 'yellow',
            self::HARASSMENT => 'red',
            self::INAPPROPRIATE => 'orange',
            self::COPYRIGHT => 'blue',
        };
    }
}

// 2. Configurer le cast
'enum_casts' => [
    'reports' => [
        'type' => ReportType::class,
    ],
],

// 3. Utilisation
$report = Report::create([
    'reporter_id' => 1,
    'reportable_id' => 1,
    'type' => ReportType::SPAM,
]);

$type = $report->type; // ReportType::SPAM
$color = $type->getColor(); // 'yellow'
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Valeur non convertible | `InvalidArgumentException` | `Invalid enum value for table "{table}", column "{column}". Expected instance of EnumerableInterface, or a valid string/int, got {type}` |
| Enum non trouvé dans la config | Aucune (retourne `null`) | - |
| Enum n'implémente pas `EnumerableInterface` | Aucune (retourne `null`) | - |

## Intégration

### Avec RepositoryConfig

`EnumCast` utilise `RepositoryConfigInterface` pour déterminer la classe enum à utiliser.

```php
$enumClass = $this->config->getEnumCast($table, $key);
```

### Avec EnumerableInterface

Tous les enums utilisés avec `EnumCast` doivent implémenter `EnumerableInterface`.

```php
interface EnumerableInterface
{
    public function getValue(): string|int;
    public static function cases(): array;
    public static function tryFrom(string|int $value): ?static;
}
```

### Avec le modèle Eloquent

```php
class Product extends Model
{
    protected $casts = [
        'status' => EnumCast::class,
    ];
}
```

## Performance

- **O(1)** : Conversion directe sans boucle
- **Sans cache** : Les enums sont résolus à chaque accès
- **Lazy loading** : Le cast ne s'exécute que lorsque l'attribut est accédé
- **Optimisation** : Utilise `tryFrom()` natif de PHP 8.1+ pour les enums

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet (enums natifs) |
| PHP 8.0 | ❌ Non supporté (pas d'enums) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Repository\Casts\EnumCast;
use AndyDefer\Repository\Contracts\EnumerableInterface;
use Illuminate\Database\Eloquent\Model;

// 1. Créer l'enum
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

// 2. Configurer
// config/repository.php
'enum_casts' => [
    'products' => [
        'status' => ProductStatus::class,
    ],
],

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

// 5. Filtrage
$published = Product::where('status', ProductStatus::PUBLISHED->getValue())->get();
```

## Voir aussi

- `EnumerableInterface` - Interface à implémenter pour les enums personnalisés
- `RepositoryConfig` - Configuration du cast
- `AttributeProxy` - Proxy pour les accesseurs Eloquent
- `AbstractRepository` - Repository utilisant ce cast