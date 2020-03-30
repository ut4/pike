# Autentikaatio

Pike sisältää autentikaatiomoduulin, jolla on yksi julkinen luokka `Pike\Auth\Authenticator`. Autentikaattori käyttää tietokantaa pitkäaikaisen, ja php-natiivia sessiota lyhytaikaisen tiedon tallennukseen. Tallennusmekanismit ei ole vielä kustomoitavissa.

```php
use \Pike\Auth\Authenticator;
class MyCtrl {
    public function __construct(Authenticator $auth) {
        $this->auth = $auth;
    }
    // tai..
    public function myMethod(Authenticator $auth) {
        // tee jotain $auth:llä
    }
}
```

## Sisällysluettelo

- [Käyttäjän kirjaaminen sisään](#käyttäjän-kirjaaminen-sisään)
- [Käyttäjän kirjautumistietojen haku](#käyttäjän-kirjautumistietojen-haku)
- [Käyttäjän kirjaaminen ulos](#käyttäjän-kirjaaminen-ulos)
- [Käyttäjän salasanan palautus (1. vaihe)](#käyttäjän-salasanan-palautus-1-vaihe)
- [Käyttäjän salasanan palautus (2. vaihe)](#käyttäjän-salasanan-palautus-1-vaihe)

## Käyttäjän kirjaaminen sisään

```php
try {
    $mySerializeUserToSession = function (\stdClass $user) {
        return (object) ['id' => $user->id, 'role' => (int) $user->role];
    };
    $auth->login('username', 'password', $mySerializeUserToSession);
    // ok, käyttäjä on nyt kirjattu sessioon
} catch (PikeException $e) {
    if ($e->getCode() === Authenticator::INVALID_CREDENTIAL)
        ; // Käyttäjätunnus tai salasana väärin, tee jotain
    else
        ; // Jokin muu poikkeus, tee jotain
}
```

## Käyttäjän kirjautumistietojen haku

```php
$myDataFromSession = $auth->getIdentity();
if ($myDataFromSession)
    echo $myDataFromSession->id; // bf789e1e-c99...
else
    ; // Kirjautumistietoja ei löytynyt sessiosta
```

## Käyttäjän kirjaaminen ulos

```php
$auth->logout();
// Ok, kirjautumistiedot poistettiin sessiosta
```

## Käyttäjän salasanan palautus (1. vaihe)

```php
try {
    $userNameOrEmail = 'username';
    $myMakeEmailSettings = function ($user, $resetKey, $settings) {
        //
        echo $settings->fromAddress; // ''
        echo $settings->fromName;    // ''
        echo $settings->toAddress;   // <käyttäjän $userNameOrEmail email>
        echo $settings->toName;      // <käyttäjän $userNameOrEmail username>
        echo $settings->subject;     // ''
        echo $settings->body;        // ''
        //
        $settings->fromAddress = 'root@my-site.com';
        $settings->subject = 'Salasanan palautus';
        $settings->body = sprintf(
            'Vaihda salasana osoitteessa: %s. Linkki on voimassa %d tuntia.',
            "my-site.com/my-finalize-password-route/{$resetKey}",
            intval(Authenticator::RESET_KEY_EXPIRATION_SECS / 60 / 60)
        );
    };
    $auth->requestPasswordReset($userNameOrEmail, $myMakeEmailSettings);
    // Ok, salasanan resetointipyyntötiedot tallennettiin $userNameOrEmail-
    // käyttäjän tietoihin tietokantaan, ja lähetettiin myMakeEmailSettings-
    // closuressa määritelty sähköposti 
} catch (PikeException $e) {
    if ($e->getCode() === Authenticator::INVALID_CREDENTIAL)
        ; // Käyttäjää $userNameOrEmail ei löytynyt
    elseif ($e->getCode() === Authenticator::FAILED_TO_FORMAT_MAIL)
        ; // myMakeEmailSettings jätti jotain täyttämättä
    elseif ($e->getCode() === Authenticator::FAILED_TO_SEND_MAIL)
        ; // sähköpostin lähetys epäonnistui
    else
        ; // Jokin muu poikkeus, tee jotain
}
```

## Käyttäjän salasanan palautus (2. vaihe)

```php
try {
    $auth->finalizePasswordReset('<key>', 'email', 'newPassword');
    // Ok, uusi salasana päivitettiin tietokantaan ja resetointipyyntötiedot
    // tyhjennettiin tietokannasta
} catch (PikeException $e) {
    if ($e->getCode() === Authenticator::INVALID_CREDENTIAL) {
        ; // resetointiavainta ei ollut olemassa, se oli vanhentunut, tai
          // $email ei täsmännyt
    else
        ; // Jokin muu poikkeus, tee jotain
}
```
