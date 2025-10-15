# <a href="https://movemove.com.ru/" target="_blank"><img src="https://avatars.githubusercontent.com/u/121749444?s=400&u=682a6bac6ba993a2a90ec220cfa205540d9d684b&v=4" width="20"></a>  VK ID (OAuth 2.1 + PKCE)
[![Latest Stable Version](http://poser.pugx.org/movemoveapp/vkid/v)](https://packagist.org/packages/movemoveapp/vkid)
[![Total Downloads](http://poser.pugx.org/movemoveapp/vkid/downloads)](https://packagist.org/packages/movemoveapp/vkid)
[![Latest Unstable Version](http://poser.pugx.org/movemoveapp/vkid/v/unstable)](https://packagist.org/packages/movemoveapp/vkid)
[![License](http://poser.pugx.org/movemoveapp/vkid/license)](https://packagist.org/packages/movemoveapp/vkid)
[![PHP Version Require](http://poser.pugx.org/movemoveapp/vkid/require/php)](https://packagist.org/packages/movemoveapp/vkid)

[На Русском](README.ru.md)

```bash
composer require movemoveapp/vkid
```

## Регистрация приложения

Создайте новое приложение VK ID на [id.vk.ru](https://id.vk.ru/apps).  
Обязательно укажите корректный redirect URI — он должен **в точности** совпадать с тем, что указано в настройках вашего приложения и в конфиге.

---

## Установка и базовое использование

Перед началом ознакомьтесь с [основным руководством по установке Socialite Providers](https://socialiteproviders.com/usage/),  
а затем следуйте инструкциям ниже, специфичным для VK ID.

---

### Добавьте конфигурацию в `config/services.php`

```php
'vkid' => [
    'client_id'     => env('VKID_CLIENT_ID'),
    'client_secret' => env('VKID_CLIENT_SECRET'),
    'redirect'      => env('VKID_REDIRECT_URI'),

    // --- Расширенные настройки ---
    'scopes'        => env('VKID_SCOPES', ['email']),          // по умолчанию: ['email'], поддерживаются ['email','phone']
    'pkce_ttl'      => env('VKID_PKCE_TTL', 10),               // время жизни кода в минутах
    'cache_store'   => env('VKID_CACHE_STORE', 'redis'),       // хранилище кеша для PKCE verifier
    'cache_prefix'  => env('VKID_CACHE_PREFIX', 'socialite:vkid:pkce:'),
    'api_version'   => env('VKID_API_VERSION', '5.199'),       // версия VK API
],
```

---

### Добавление слушателя события провайдера

#### Laravel 11+

Так как в Laravel 11 был удалён `EventServiceProvider`, добавьте слушатель с помощью фасада `Event`  
в метод `boot()` вашего `AppServiceProvider`.

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\VKID\VKIDExtendSocialite;

public function boot(): void
{
    Event::listen(SocialiteWasCalled::class, [VKIDExtendSocialite::class, 'handle']);
}
```

<details>
<summary>Laravel 10 и ниже</summary>

Добавьте слушатель в `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        \SocialiteProviders\VKID\VKIDExtendSocialite::class.'@handle',
    ],
];
```
</details>

---

### Использование

Теперь можно использовать драйвер VK ID как стандартный драйвер Socialite:

```php
// Перенаправление пользователя на VK ID
return Socialite::driver('vkid')->redirect();
```

Для приложений с расширенными правами (например, бизнес-аккаунт с доступом к номеру телефона):

```php
return Socialite::driver('vkid')
    ->scopes(['email', 'phone'])
    ->redirect();
```

#### Обработка callback

```php
try {
    $vkUser = Socialite::driver('vkid')->user();

    // Доступные поля:
    // $vkUser->getId(), getNickname(), getName(), getEmail(), getAvatar()
    // Номер телефона (если запрошен scope=phone и разрешён в приложении):
    // $vkUser->user['phone'] ?? null

    // Пример логики авторизации
    $localUser = User::firstOrCreate(
        ['email' => $vkUser->getEmail() ?? "vk_{$vkUser->getId()}@example.local"],
        ['name' => $vkUser->getName()]
    );

    Auth::login($localUser);
    return redirect()->intended('/');
} catch (\Laravel\Socialite\Two\InvalidStateException $e) {
    // Истёк срок действия PKCE verifier — пользователь слишком долго ждал
    return redirect()->route('login')->with('auth_error', 'Время авторизации истекло. Попробуйте ещё раз.');
} catch (\RuntimeException $e) {
    // Проблема с кэшем или его конфигурацией
    report($e);
    return redirect()->route('login')->with('auth_error', 'Ошибка авторизации через VK ID. Попробуйте позже.');
} catch (\Throwable $e) {
    report($e);
    return redirect()->route('login')->with('auth_error', 'Неизвестная ошибка авторизации через VK ID.');
}
```

---

### Возвращаемые поля пользователя

| Поле       | Описание                                                             |
|------------|----------------------------------------------------------------------|
| `id`       | Идентификатор пользователя VK                                        |
| `nickname` | Короткое имя (`screen_name`, если доступно)                          |
| `name`     | Полное имя (Имя + Фамилия)                                           |
| `email`    | Адрес электронной почты (если запрошен `scope=email`)                |
| `avatar`   | URL аватара (200px)                                                  |
| `phone`    | Номер телефона (если запрошен `scope=phone` и разрешён в приложении) |

---

### Возможные исключения

| Исключение                                                                     | Причина                                                                                        | Как обработать                                                                   |
|--------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------|
| `Laravel\Socialite\Two\InvalidStateException`                                  | - Отсутствует `state` или PKCE verifier<br>- Пользователь слишком долго не нажимал «Разрешить» | Сообщите пользователю, что время авторизации истекло, и предложите начать заново |
| `RuntimeException("VKID PKCE cache failure")`                                  | Ошибка при сохранении или чтении кода PKCE из кеша (например, Redis недоступен)                | Проверьте настройки `config/cache.php` и параметр `VKID_CACHE_STORE`             |
| `RuntimeException("VKID PKCE cache failure: unable to persist code_verifier")` | Проблема с конфигурацией кеша или правами записи                                               | Убедитесь, что используемое хранилище кеша работает корректно                    |
| Любое другое исключение                                                        | Ошибка сети или неожиданный формат данных                                                      | Выведите сообщение «Ошибка входа через VK ID» и логируйте проблему               |

---

### Расширенные настройки

| Ключ            | Тип    | Значение по умолчанию    | Описание                               |
|-----------------|--------|--------------------------|----------------------------------------|
| `client_id`     | string | —                        | Идентификатор приложения VK            |
| `client_secret` | string | —                        | Секретный ключ приложения              |
| `redirect`      | string | —                        | Полный URL обратного вызова (callback) |
| `scopes`        | array  | `['email']`              | Запрашиваемые права                    |
| `pkce_ttl`      | int    | `10`                     | Время хранения PKCE verifier (минуты)  |
| `cache_store`   | string | `'redis'`                | Хранилище для PKCE verifier            |
| `cache_prefix`  | string | `'socialite:vkid:pkce:'` | Префикс ключей в кеше                  |
| `api_version`   | string | `'5.199'`                | Версия VK API для запроса профиля      |

---

### Пример `.env`

```dotenv
VKID_CLIENT_ID=1234567
VKID_CLIENT_SECRET=secretkey
VKID_REDIRECT_URI=https://your-app.ru/auth/vkid/callback

VKID_SCOPES="['email','phone']"
VKID_PKCE_TTL=10
VKID_CACHE_STORE=redis
VKID_CACHE_PREFIX=socialite:vkid:pkce:
VKID_API_VERSION=5.199
```

---

### Пример пользовательского потока

```flow js
Клиент  →  https://id.vk.ru/authorize?response_type=code&state=XYZ...
   ↳ Редирект на /auth/vkid/callback?code=...&state=XYZ
Провайдер  →  Обмен кода на токен (с помощью code_verifier)
   ↳ Запрос профиля через api.vk.com/method/users.get
Приложение  →  Создаёт/авторизует пользователя и сохраняет email/аватар/телефон
```

---

### Дополнительные материалы

- [VK ID для разработчиков (OAuth 2.1)](https://id.vk.com/docs)
- [VK API Reference](https://dev.vk.com/method/users.get)
- [Документация Laravel Socialite](https://laravel.com/docs/socialite)
- [Socialite Providers: Usage Guide](https://socialiteproviders.com/usage/)
