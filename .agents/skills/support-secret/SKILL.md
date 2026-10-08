---
name: support-secret
description: Encrypted .env secret resolution (AES-256-GCM, Sodium). Use for Secret, SecretResolver, encryption keys, jardissupport/secret.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns, support-dotenv]
next: []
---

# SECRET_COMPONENT_SKILL
> jardissupport/secret v1.1 | NS: `JardisSupport\Secret` | PHP 8.2+ | ext-openssl, ext-sodium

## ENCRYPTION FORMATS
| Resolver | Algorithm | Key | Nonce | Format |
|----------|-----------|-----|-------|--------|
| `AesSecretResolver` | AES-256-GCM | 32 bytes | 12 bytes | `[aes:]base64(nonce[12] + ciphertext + tag[16])` |
| `SodiumSecretResolver` | XSalsa20-Poly1305 | 32 bytes | 24 bytes | `sodium:base64(nonce[24] + ciphertext_with_mac)` |

## PREFIX STRATEGY
| Prefix | Resolver | `supports()` logic |
|--------|----------|--------------------|
| `sodium:` | `SodiumSecretResolver` | `str_starts_with($v, 'sodium:')` |
| `aes:` | `AesSecretResolver` | `str_starts_with($v, 'aes:')` |
| (no prefix) | `AesSecretResolver` | catch-all: `!str_contains($v, ':')` |

**Chain order:** register specific resolvers (Sodium) BEFORE the AES catch-all.

## INTERFACE (jardissupport/contracts)
```php
interface SecretResolverInterface {
    public function supports(string $encryptedValue): bool;
    public function resolve(string $encryptedValue): string; // throws SecretResolutionException
}
```

## API SIGNATURES
```php
// SecretHandler — recommended, wires full chain (Sodium + AES)
new SecretHandler(callable $keyProvider);
$handler($value);  // __invoke(?string): ?string

// Secret — DotEnv cast plugin
new Secret(?SecretResolverInterface $resolver = null);
$caster($value);   // __invoke(?string): ?string
// null → null | "plain" → "plain" | "secret(x)" → resolve("x") | no resolver → unchanged
Secret::PATTERN;                     // '/^secret\((.+)\)$/' — the one marker format
Secret::matches(string $value): bool // static format check, no resolver/key/decryption

// SecretResolverChain — immutable
new SecretResolverChain(array $resolvers = []);
$chain->addResolver(SecretResolverInterface): static  // returns clone
$chain->supports(string $value): bool
$chain->resolve(string $value): string  // throws SecretResolutionException if no match

// AesSecretResolver / SodiumSecretResolver
new AesSecretResolver(string|callable $key);
new SodiumSecretResolver(string|callable $key);
AesSecretResolver::encrypt(string $plaintext, string|callable $key): string     // base64
SodiumSecretResolver::encrypt(string $plaintext, string|callable $key): string  // base64

// Key Providers (invokable, lazy)
new FileKeyProvider(string $path);   // auto-detects base64 vs raw
new EnvKeyProvider(string $envVar);  // auto-detects base64 vs raw
```

## MARKER DETECTION
```php
use JardisSupport\Secret\Secret;

Secret::matches('secret(base64value)');       // true
Secret::matches('secret(sodium:base64value)');// true
Secret::matches('plain-value');               // false
Secret::matches('Secret(x)');                 // false — case-sensitive
Secret::matches('secret()');                  // false — payload must be non-empty
```
Same rule `__invoke()` applies before resolving. Consumers (e.g. a kernel bootstrap asking
"is this value a ciphertext marker?") use `Secret::matches()` / `Secret::PATTERN` — **never**
a copied regex.

## USAGE — DOTENV INTEGRATION (recommended)
```php
use JardisSupport\Secret\Handler\SecretHandler;
use JardisSupport\Secret\KeyProvider\FileKeyProvider;
use JardisSupport\DotEnv\DotEnv;

$dotEnv = new DotEnv();
$dotEnv->addHandler(new SecretHandler(new FileKeyProvider('support/secret.key')), prepend: true);
$config = $dotEnv->loadPrivate('/path/to/app');
```

`.env` format:
```
DB_PASSWORD=secret(base64value)           # AES without prefix
DB_PASSWORD=secret(aes:base64value)       # AES with prefix
API_KEY=secret(sodium:base64value)        # Sodium
```

## MAKEFILE TOOLING
```bash
make generate-key-file                     # → support/secret.key (base64, chmod 600)
make encrypt VALUE="plaintext"             # → secret(aes-encrypted)
make encrypt-sodium VALUE="plaintext"      # → secret(sodium:encrypted)
make encrypt KEY_FILE=other.key VALUE="x"  # custom key file
```

## EXCEPTIONS
| Exception | Trigger | Namespace |
|-----------|---------|-----------|
| `SecretResolutionException` | base (contract) | `JardisSupport\Contract\Secret` |
| `SecretException` | base (package) | `JardisSupport\Secret\Exception` |
| `InvalidKeyException` | key missing, wrong length, file not readable | extends `SecretException` |
| `DecryptionFailedException` | Base64 invalid, decryption failed | extends `SecretException` |
| `EncryptionFailedException` | AES-256-GCM encryption failed | extends `SecretException` |

## RULES
- **NEVER** store key in code/repo/`.env`; call `encrypt()` at runtime; register AES catch-all BEFORE specific resolvers
- **NEVER** duplicate the `secret(...)` regex in a consumer — call `Secret::matches()` (or reference `Secret::PATTERN`)
- **ALWAYS** `addHandler($handler, prepend: true)` for DotEnv integration; store key separately from ciphertext; Sodium BEFORE AES in chain; `*.key` in `.gitignore`
