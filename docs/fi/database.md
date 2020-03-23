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
    $numAffectedRows = $db->fetchOne("DELETE FROM Products WHERE `id`=?", [1]);
    if ($numAffectedRows)
        ; // ok
    else
        ; // tee jotain
} catch (\PDOException $e) {
    ; // Handlaa tilanne
}
```
