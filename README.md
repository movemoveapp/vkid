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

## Register an application

Create a new VK ID application at [id.vk.ru](https://id.vk.com/about/business/?utm_source=movemove.com.ru&utm_medium=partner_referral&utm_campaign=vkid_integration&utm_content=laravel+sdk).
Make sure you specify the correct redirect URI (must match exactly with the one in your config).

## Installation & Basic Usage
Please see the [Base Installation Guide](https://socialiteproviders.com/usage/) first, then follow the VK ID-specific instructions below.

### Add configuration to `config/services.php`

```php
'vkid' => [
    'client_id'     => env('VKID_CLIENT_ID'),
    'client_secret' => env('VKID_CLIENT_SECRET'),
    'redirect'      => env('VKID_REDIRECT_URI'),

    // --- Extended configuration ---
    'scopes'        => env('VKID_SCOPES', ['email']),                       // default: ['email'], supports ['email','phone']
    'pkce_ttl'      => env('VKID_PKCE_TTL', 10),                            // minutes to store code_verifier
    'cache_store'   => env('VKID_CACHE_STORE', 'redis'),                    // cache store for PKCE verifier
    'cache_prefix'  => env('VKID_CACHE_PREFIX', 'socialite:vkid:pkce:'),
    'api_version'   => env('VKID_API_VERSION', '5.199'),                    // VK API version
],
```

### Add provider event listener

#### Laravel 11+
Since Laravel 11 removed `EventServiceProvider`, use the Event facade in your `AppServiceProvider@boot`:

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use MoveMoveApp\VKID\VKIDExtendSocialite;

public function boot(): void
{
    Event::listen(SocialiteWasCalled::class, [VKIDExtendSocialite::class, 'handle']);
}
```

<details>
<summary>
Laravel 10 or below
</summary>
Add the listener in your `app/Providers/EventServiceProvider.php`. See the [Base Installation Guide](https://socialiteproviders.com/usage/) for detailed instructions.

```php
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        MoveMoveApp\VKID\VKIDExtendSocialite::class.'@handle',
    ],
];
```
</details>

### Usage

You can now use the driver as usual with Socialite:

```php
return Socialite::driver('vkid')->redirect();
```

For extended scopes (e.g. apps with Business Account permission):

```php
return Socialite::driver('vkid')
    ->scopes(['email', 'phone'])
    ->redirect();
```

Handling the callback:

```php
try {
    $vkUser = Socialite::driver('vkid')->user();

    // Typical fields:
    // $vkUser->getId(), getNickname(), getName(), getEmail(), getAvatar()
    // Optional phone (if scope=phone and app has permission):
    // $vkUser->user['phone'] ?? null

    // Your app logic:
    $localUser = User::firstOrCreate(
        ['email' => $vkUser->getEmail() ?? "vk_{$vkUser->getId()}@example.local"],
        ['name' => $vkUser->getName()]
    );

    Auth::login($localUser);
    return redirect()->intended('/');
} catch (\Laravel\Socialite\Two\InvalidStateException $e) {
    // Thrown when PKCE verifier expired (user waited too long)
    return redirect()->route('login')->with('auth_error', 'Authorization expired. Please try again.');
} catch (\RuntimeException $e) {
    // Thrown when cache store is unavailable/misconfigured
    report($e);
    return redirect()->route('login')->with('auth_error', 'Temporary VK ID authorization error.');
} catch (\Throwable $e) {
    report($e);
    return redirect()->route('login')->with('auth_error', 'Unexpected VK ID login error.');
}
```

### Returned User fields

| Field      | Description                                |
|------------|--------------------------------------------|
| `id`       | VK user ID                                 |
| `nickname` | screen_name (if present)                   |
| `name`     | First + last name                          |
| `email`    | from id_token (if scope=email)             |
| `avatar`   | URL to 200px profile photo                 |
| `phone`    | from id_token (if scope=phone and granted) |

### Exception Reference

| Exception                                                                      | Cause                                                                                                         | Recommended handling                                                                    | 
|--------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|
| `Laravel\Socialite\Two\InvalidStateException`                                  | - state or PKCE verifier missing or expired- user waited longer than pkce_ttl minutes before pressing “Allow” | Ask user to restart login. Show message like “Authorization expired. Please try again.” |
| `RuntimeException("VKID PKCE cache failure")`                                  | Cache store misconfigured or unavailable (e.g., Redis down)                                                   | Log and retry later. Typically a server-side infra issue.                               |
| `RuntimeException("VKID PKCE cache failure: unable to persist code_verifier")` | Misconfigured cache_store or permission issue writing PKCE state                                              | Ensure correct cache driver in config/cache.php                                         |
| `Any other exception`                                                          | Networking or unexpected JSON format                                                                          | Generic fallback “VK ID login failed”                                                   |

All exceptions extend base \Throwable and can be caught globally in your app’s Handler.

### Extended Configuration Options

| Key            | Type   | Default                | Description                                 |
|----------------|--------|------------------------|---------------------------------------------|
| `client_id     | string | –                      | VK App ID                                   |
| `client_secret | string | –                      | VK App secret                               |
| `redirect      | string | –                      | Full callback URL (must match app settings) |
| `scopes        | array  | ['email']              | Default OAuth scopes                        |
| `pkce_ttl      | int    | 10                     | Time in minutes to store PKCE verifier      |
| `cache_store   | string | 'redis'                | Cache store for PKCE (see config/cache.php) |
| `cache_prefix  | string | 'socialite:vkid:pkce:' | Prefix for PKCE keys                        |
| `api_version   | string | '5.199'                | VK API version for users.get                |


### Example .env
```dotenv
VKID_CLIENT_ID=1234567
VKID_CLIENT_SECRET=secretkey
VKID_REDIRECT_URI=https://your-app.com/auth/vkid/callback

VKID_SCOPES="['email','phone']"
VKID_PKCE_TTL=10
VKID_CACHE_STORE=redis
VKID_CACHE_PREFIX=socialite:vkid:pkce:
VKID_API_VERSION=5.199
```

### Example User Flow Diagram

```flow js
Client  →  https://id.vk.ru/authorize?response_type=code&state=XYZ...
   ↳ Redirects user back to /auth/vkid/callback?code=...&state=XYZ
Provider  →  Exchanges code for tokens (with code_verifier)
   ↳ Fetches user profile via api.vk.com/method/users.get
App  →  Creates/logs in user, handles avatar/email/phone sync
```

---

### Reference

- [VK ID Developer Docs (OAuth 2.1)](https://id.vk.com/docs)
- [VK API Reference](https://dev.vk.com/method/users.get)
- [Laravel Socialite Documentation](https://laravel.com/docs/12.x/socialite)
- [SocialiteProviders.com Usage Guide](https://socialiteproviders.com/usage/)