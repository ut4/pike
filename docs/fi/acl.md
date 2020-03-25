# Käyttäjäroolit ja oikeudet

Pike sisältää `\Pike\Auth\ACL`-luokan, jolla on helppo luoda systeemi, jolla tarkastaa mitä kukin applikaation käyttäjärooli saa, ja ei saa tehdä.

## Sisällysluettelo

- [Peruskäyttö](#peruskäyttö)

## Peruskäyttö

Setuppi:
```php
use \Pike\Auth\ACL;
$resources = (object) [
    'products' => (object) [
        'create'  => 1 << 1,
        'edit'    => 1 << 2,
        'comment' => 1 << 3,
    ],
    'reviews' => (object) [
        'post'     => 1 << 1,
        'moderate' => 1 << 2,
    ]
];
$userPermissions = (object) [
    ACL::ROLE_EDITOR => (object) [
        'products' => ACL::makePermissions(['comment', 'edit'], $resources->products),
        'reviews'  => ACL::makePermissions('*', $resources->reviews),
    ],
    ACL::ROLE_CONTRIBUTOR => (object) [
        'products' => ACL::makePermissions(['comment'], $resources->products),
        'reviews'  => ACL::makePermissions(['post'], $resources->reviews),
    ]
];
$acl = new ACL;
$acl->setRules((object)['resources' => $resources,
                        'userPermissions' => $userPermissions]);
```

Käyttö:
```php
$acl->can(ACL::ROLE_EDITOR,      'create',   'products'); // false
$acl->can(ACL::ROLE_EDITOR,      'edit',     'products'); // true
$acl->can(ACL::ROLE_EDITOR,      'comment',  'products'); // true
$acl->can(ACL::ROLE_EDITOR,      'post',     'reviews');  // true
$acl->can(ACL::ROLE_EDITOR,      'moderate', 'reviews');  // true

$acl->can(ACL::ROLE_CONTRIBUTOR, 'create',   'products'); // false
$acl->can(ACL::ROLE_CONTRIBUTOR, 'edit',     'products'); // false
$acl->can(ACL::ROLE_CONTRIBUTOR, 'comment',  'products'); // true
$acl->can(ACL::ROLE_CONTRIBUTOR, 'post',     'reviews');  // true
$acl->can(ACL::ROLE_CONTRIBUTOR, 'moderate', 'reviews');  // false
```

Oletuskäyttäytyminen:
```php
// false, jos role|action|resource ei olemassa
$acl->can(NONEXISTING_ROLE, 'post',              'reviews');             // false
$acl->can(ACL::ROLE_EDITOR, 'nonExistingAction', 'reviews');             // false
$acl->can(ACL::ROLE_EDITOR, 'post',              'nonExistingResource'); // false

// true, jos kyseessä super-admin
$acl->can(ACL::ROLE_SUPER_ADMIN, 'nonExistingAction', 'reviews');             // true
$acl->can(ACL::ROLE_SUPER_ADMIN, 'post',              'nonExistingResource'); // true
$acl->can(ACL::ROLE_SUPER_ADMIN, 'kissat koiria',     'gfffögkfhjd');         // true
```
