# Tietokanta

Pikessä tietokantakyselyt suoritetaan `\Pike\Db`-luokalla, joka on on ohut wräpperi `\PDO`-abstraktion ympärille.

```php
use \Pike\Db;
class MyCtrl {
    public function __construct(Db $db) {
        $this->db = $db;
    }
    // tai..
    public function myMethod(Db $db) {
        // tee jotain $db:llä
    }
}
```

## Sisällysluettelo

- [Käyttöönotto](#käyttöönotto-mariadbmysql)
- [Datan insertointi](#datan-insertointi)
- [Datan hakeminen, useita rivejä](#datan-hakeminen-useita-rivejä)
- [Datan hakeminen, yksi rivi](#datan-hakeminen-yksi-rivi)
- [Datan päivittäminen](#datan-päivittäminen)
- [Datan poistaminen](#datan-poistaminen)

## Käyttöönotto (MariaDb/MySQL)

`\Pike\Db`:n konfigrointiin tarvitaan kolme asiaa:
- `\Pike\App::create()`:een passattu `$config`, jossa vähintään `db.host => 'myval'`
- `\Pike\App::create()`:een passattu `$ctx`, jossa `'db' => \Pike\App::MAKE_AUTOMATICALLY`
- `$db->open()`-kutsu

Näiden jälkeen `\Pike\Db` injektoituu minkä tahansa kontrollerin konstruktoriin tai metodiin type-hinttien perusteella (ks. [examples/hello-world.md](examples/hello-world.md#helloworldsomecontrollerphp) mikäli et muista miten tämä tapahtuu). `\Pike\Db` -luokasta luodaan vain yksi instanssi, ks. [\Auryn\Injector->share()](https://github.com/rdlowrey/Auryn#instance-sharing).

Esimerkki:

### index.php

```php
$ctx = (object) [\Pike\App::SERVICE_DB => \Pike\App::MAKE_AUTOMATICALLY];
// tai
$ctx = (object) ['db' => '@auto'];

$config = [
    'db.host'        => '127.0.0.1', // oletus '127.0.0.1'
    'db.database'    => 'new2',      // oletus ''
    'db.user'        => 'devuser',   // oletus ''
    'db.pass'        => 'qweqwe',    // oletus ''
    'db.tablePrefix' => 'rad_',      // oletus ''
    'db.charset'     => 'utf8',      // oletus 'utf8'
];
// tai
$config = __DIR__ . '/config.php'; // jossa <?php return [...];

$app = \RadCms\App::create([MyBootstrapModule::class], $config, $ctx);
$app->handleRequest(...);

```

### MyBootstrapModule.php

```php
abstract class MyBootstrapModule {
    /**
     * @param \stdClass $ctx {\Pike\Db db, \Pike\Router router}
     */
    public static function init(\stdClass $ctx) {
        try {
            $ctx->db->open();
        } catch (\Pike\PikeException $e) {
            // Tee jotain
        }
    }
}

```

## Datan insertointi

```php
$data = (object) ['foo' => 'value', 'bar' => 'another value'];
[$qs, $vals, $columns] = \Pike\DbUtils::makeInsertBinders($data);
try {
    $insertId = $db->exec("INSERT INTO `Products` ({$columns}) VALUES ({$qs})",
                          $vals);
    if ($insertId > 0)
        ; // ok
    else
        ; // Tee jotain
} catch (\PDOException $e) {
    ; // Handlaa tilanne
}
```

## Datan hakeminen, useita rivejä

```php
try {
    $rows = $db->fetchAll("SELECT `foo`,`bar` FROM Products WHERE `id`<?", [3]);
    if ($rows)
        echo $rows[0]['foo']; // 'value'
    else
        ; // tee jotain
} catch (\PDOException $e) {
    ; // Handlaa tilanne
}
```

## Datan hakeminen, yksi rivi

```php
try {
    $row = $db->fetchOne("SELECT `foo`,`bar` FROM Products WHERE `id`=?", [1]);
    if ($row)
        echo $rows['foo']; // 'value'
    else
        ; // tee jotain
} catch (\PDOException $e) {
    ; // Handlaa tilanne
}
```

## Datan päivittäminen

```php
$data = (object) ['foo' => 'value', 'bar' => 'another value'];
[$columns, $vals] = \Pike\DbUtils::makeUpdateBinders($data);
try {
    $numAffectedRows = $db->exec("UPDATE `Products` SET {$columns} WHERE `id`=?",
                                 array_merge($vals, [1]));
    if ($numAffectedRows > 0)
        ; // ok
    else
        ; // Tee jotain
} catch (\PDOException $e) {
    ; // Handlaa tilanne
}
```

## Datan poistaminen

```php
try {
    $numAffectedRows = $db->exec("DELETE FROM Products WHERE `id`=?", [1]);
    if ($numAffectedRows)
        ; // ok
    else
        ; // tee jotain
} catch (\PDOException $e) {
    ; // Handlaa tilanne
}
```
