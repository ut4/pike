# Validaatio

Pike sisältää luokat [olioiden](#olioiden-validointi), ja yksittäisten [arvojen](#yksittäisten-arvojen-validointi) validoimiseen.

## Sisällysluettelo

- [Olioiden validointi](#olioiden-validointi)
- [Yksittäisten arvojen validointi](#yksittäisten-arvojen-validointi)
- [Oletusvalidaattorit](#oletusvalidaattorit)
    - [rule('type', 'string'|'int'|'array'|'bool'|'float'|'object` $expectedDataType)](#ruletype-stringintarrayboolfloatobject-expecteddatatype)
    - [rule('minLength', int $minLength)](#ruleminlength-int-minlength)
    - [rule('maxLength', int $maxLength)](#rulemaxlength-int-maxlength)
    - [rule('min', int $min)](#rulemin-int-min)
    - [rule('max', int $max)](#rulemax-int-max)
    - [rule('in', array $listOfValues)](#rulein-array-listOfValues)
    - [rule('identifier')](#ruleidentifier)

## Olioiden validointi

```php
$object = (object) [
    'foo' => 'value',
    'bar' => (object) ['key' => 'another value'],
    'baz' => [
        (object) ['key' => 'inside array'],
        (object) ['key' => 'inside array'],
    ]
];
$errors = (\Pike\Validation::makeObjectValidator())
    ->rule('foo', 'type', 'int')
    ->rule('optional?', 'type', 'int')
    ->rule('bar.key', 'minLength', 1)
    ->rule('baz.*.key', 'in', ['a', 'b'])
    ->validate($object);
if (!$errors)
    ; // Ok, $errors == []
else
    ; // Fail, $errors == ['Virheviesti', 'Toinen virheviesti' ...]
```

## Yksittäisten arvojen validointi

```php
$value = 'value';
$errors = (\Pike\Validation::makeValueValidator())
    ->rule('type', 'string')
    ->rule('minLength', 1)
    ->validate($value);
if (!$errors)
    ; // Ok, $errors == []
else
    ; // Fail, $errors == ['Virheviesti', 'Toinen virheviesti' ...]
```

## Oletusvalidaattorit

### rule('type', 'string'|'int'|'array'|'bool'|'float'|'object' $expectedDataType)

Tarkastaa onko arvo tyyppiä `$expectedDataType`.

```php
$valueValidator->rule('type', 'string')->validate([]);    // Errors
$valueValidator->rule('type', 'string')->validate('str'); // Ok
```

### rule('minLength', int $minLength)

Tarkastaa onko arvo merkkijono tai countable, jonka `mb_strlen()` tai `count()` arvo on suurempi, tai yhtä suuri kuin `$minLength`.

```php
$valueValidator->rule('minLength', 2)->validate('s');   // Errors
$valueValidator->rule('minLength', 2)->validate('st');  // Ok
$valueValidator->rule('minLength', 2)->validate([1]);   // Errors
$valueValidator->rule('minLength', 2)->validate([1,2]); // Ok
```

### rule('maxLength', int $maxLength)

Tarkastaa onko arvo merkkijono tai countable, jonka `mb_strlen()` tai `count()` arvo on pienempi, tai yhtä suuri kuin `$maxLength`.

```php
$valueValidator->rule('maxLength', 2)->validate('str');   // Errors
$valueValidator->rule('maxLength', 2)->validate('st');    // Ok
$valueValidator->rule('maxLength', 2)->validate([1,2,3]); // Errors
$valueValidator->rule('maxLength', 2)->validate([1,2]);   // Ok
```

### rule('min', int $min)

Tarkastaa onko arvo numero, jonka arvo on enemmän, tai yhtä suuri kuin `$min`.

```php
$valueValidator->rule('min', 5)->validate(1);     // Errors
$valueValidator->rule('min', 5)->validate('1');   // Errors
$valueValidator->rule('min', 5)->validate('foo'); // Errors
$valueValidator->rule('min', 5)->validate([]);    // Errors
$valueValidator->rule('min', 5)->validate(6);     // Ok
$valueValidator->rule('min', 5)->validate('6.0'); // Ok
$valueValidator->rule('min', 5)->validate(5);     // Ok
```

### rule('max', int $max)

Tarkastaa onko arvo numero, jonka arvo on vähemmän, tai yhtä suuri kuin `$max`.

```php
$valueValidator->rule('max', 5)->validate(6);     // Errors
$valueValidator->rule('max', 5)->validate('6');   // Errors
$valueValidator->rule('max', 5)->validate('foo'); // Errors
$valueValidator->rule('max', 5)->validate([]);    // Errors
$valueValidator->rule('max', 5)->validate(2);     // Ok
$valueValidator->rule('max', 5)->validate('2.0'); // Ok
$valueValidator->rule('max', 5)->validate(5);     // Ok
```

### rule('in', array $listOfValues)

Tarkastaa löytyykö arvo taulukosta `$listOfValues`.

```php
$valueValidator->rule('in', [1, 2])->validate(6);     // Errors
$valueValidator->rule('in', [1, 2])->validate('foo'); // Errors
$valueValidator->rule('in', [1, 2])->validate('2');   // Errors (väärä tietotyyppi)
$valueValidator->rule('in', [1, 2])->validate(2);     // Ok
```

### rule('identifier')

Tarkastaa onko arvo merkkijono, joka:
- Alkaa kirjaimella a-zA-Z tai _
- Sisältää ainoastaan a-zA-Z0-9 tai _

```php
$valueValidator->rule('identifier')->validate([]);     // Errors (ei string)
$valueValidator->rule('identifier')->validate('Ab#');  // Errors (non-ascii)
$valueValidator->rule('identifier')->validate('Abä');  // Errors (non-ascii)
$valueValidator->rule('identifier')->validate('4foo'); // Errors (alkaa numerolla)
$valueValidator->rule('identifier')->validate('Abc');  // Ok
$valueValidator->rule('identifier')->validate('Ab_c'); // Ok
$valueValidator->rule('identifier')->validate('Ab5');  // Ok
```
