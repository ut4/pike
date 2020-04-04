# Tiedostot

`\Pike\FileSystem` on abstraktio yleisimpien php-natiivien tiedostonhallintafunktioiden (`is_dir()`, `file_get_contents()`) ympärille.

```php
use \Pike\FileSystemInterface;
class MyCtrl {
    public function __construct(FileSystemInterface $fs) {
        $this->fs = $fs;
    }
    // tai..
    public function myMethod(FileSystemInterface $fs) {
        // tee jotain $fs:llä
    }
}

```

## Sisällysluettelo

- [Tiedoston luominen, tiedostoon kirjoittaminen](#tiedoston-luominen-tiedostoon-kirjoittaminen)
- [Tiedoston lukeminen](#tiedoston-lukeminen)
- [Tiedoston poistaminen](#tiedoston-poistaminen)
- [Tiedoston kopioiminen](#tiedoston-kopioiminen)
- [Kansion luominen](#kansion-luominen)
- [Kansion poistaminen](#kansion-poistaminen)
- [Tiedostopolun tarkistaminen](#tiedostopolun-tarkistaminen)
- [Kansiopolun tarkistaminen](#kansiopolun-tarkistaminen)
- [Kansion sisällön lukeminen (ei-rekursiivinen)](#kansion-sisällön-lukeminen-ei-rekursiivinen)
- [Kansion sisällön lukeminen (rekursiivinen)](#kansion-sisällön-lukeminen-rekursiivinen)
- [Tiedoston viimeisimmän modifikaation lukeminen](#tiedoston-viimeisimmän-modifikaation-lukeminen)
- [Polun normalisointi](#polun-normalisointi)

## Tiedoston luominen, tiedostoon kirjoittaminen

```php
$numBytesWritten = $fs->write(__DIR__ . '/tiedosto.txt', 'Sisältö');
if ($numBytesWritten !== false)
    ; // ok
else
    ; // Handlaa failure
```

## Tiedoston lukeminen

```php
$contents = $fs->read(__DIR__ . '/tiedosto.txt');
if ($contents !== false)
    ; // ok
else
    ; // Handlaa failure
```

## Tiedoston poistaminen

```php
$ok = $fs->unlink(__DIR__ . '/tiedosto.txt');
if ($ok)
    ; // ok
else
    ; // Handlaa failure
```

## Tiedoston kopioiminen

Kohdepolun kansio tulee olla olemassa. Jos kohdetiedosto on jo olemassa, se ylikirjoitetaan.

```php
$ok = $fs->copy(__DIR__ . '/tiedosto.txt', __DIR__ . '/copied.txt');
if ($ok)
    ; // ok
else
    ; // Handlaa failure
```

## Kansion luominen

```php
$perms = 0755;      // oletus 0777
$recursive = false; // oletus true
$ok = $fs->mkDir(__DIR__ . '/kansio', $perms, $recursive);
if ($ok)
    ; // ok
else
    ; // Handlaa failure
```

## Kansion poistaminen

```php
$ok = $fs->rmDir(__DIR__ . '/kansio');
if ($ok)
    ; // ok
else
    ; // Handlaa failure
```

## Tiedostopolun tarkistaminen

```php
$isFile = $fs->isFile(__DIR__ . '/tiedosto.txt');
if ($isFile)
    ; // on tiedosto
else
    ; // ei ole tiedosto
```

## Kansiopolun tarkistaminen

```php
$isDir = $fs->isDir(__DIR__ . '/kansio');
if ($isDir)
    ; // on kansio
else
    ; // ei ole kansio
```

## Kansion sisällön lukeminen (ei-rekursiivinen)

```php
$globPattern = '*.txt'; // oletus '*',
$globFlags = null;      // oletus GLOB_ERR
$fullFilePaths = $fs->readDir(__DIR__ . '/kansio', $globPattern, $globFlags);
if ($fullFilePaths !== false)
    echo $fullFilePaths[0]; // /htdocs/projekti/kansio/foo.txt
else
    ; // Handlaa failure
```

## Kansion sisällön lukeminen (rekursiivinen)

```php
$regexpPattern = '/^.*\.(js|css)$/';
$fullFilePaths = $fs->readDirRecursive(__DIR__ . '/kansio', $regexpPattern);
if ($fullFilePaths !== false)
    echo $fullFilePaths[0]; // /htdocs/projekti/kansio/alikansio/foo.js
else
    ; // Handlaa failure
```

## Tiedoston viimeisimmän modifikaation lukeminen

```php
$unixTime = $fs->lastModTime(__DIR__ . '/tiedosto.txt');
if ($unixTime !== false)
    ; // Tee jotain $unixTimella
else
    ; // Handlaa failure
```

## Polun normalisointi

```php
$notNormalized = __DIR__ . '/foo/'; // c:\kansio\alikansio/foo/
$normalized = FileSystem::normalizePath($notNormalized);
echo $normalized;                   // c:/kansio/alikansio/foo
```
