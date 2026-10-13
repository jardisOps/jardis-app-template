---
name: adapter-filesystem
description: jardisadapter/filesystem - Local and S3 filesystem abstraction with unified API, stream support, visibility. Use when working with FilesystemService, Filesystem, file operations, or jardisadapter/filesystem.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns]
next: []
---

# FILESYSTEM_COMPONENT_SKILL
> `jardisadapter/filesystem` | NS: `JardisAdapter\Filesystem` | Local + S3 | PHP 8.2+

## ARCHITECTURE
```
FilesystemService (implements FilesystemServiceInterface)
  ├── local(root): FilesystemInterface        ← interface method
  ├── s3(bucket, region, key, secret, endpoint?, prefix?): FilesystemInterface  ← interface method
  └── create(LocalConfig|S3Config): FilesystemInterface  ← concrete service only
```

## API

### FilesystemService
```php
use JardisAdapter\Filesystem\FilesystemService;

$service = new FilesystemService();
$fs = $service->local('/var/app/storage');
$fs = $service->s3('my-bucket', 'eu-central-1', $key, $secret);
$fs = $service->s3('bucket', 'us-east-1', $key, $secret, endpoint: 'http://filesystem-s3:9000');
$fs = $service->create(new LocalConfig(root: '/storage', filePermissions: 0600));
// create() is on concrete FilesystemService only, not on FilesystemServiceInterface
```

### File Operations
```php
$fs->write('path/file.txt', $content);
$content = $fs->read('path/file.txt');
$fs->exists('file.txt');           // bool
$fs->size('file.txt');             // int (bytes)
$fs->lastModified('file.txt');     // int (Unix timestamp)
$fs->mimeType('file.txt');         // string
$fs->copy('source.txt', 'target.txt');
$fs->move('old.txt', 'new.txt');
$fs->delete('file.txt');
```

### Stream Operations
```php
$fs->writeStream('uploads/large.zip', $stream);   // resource
$stream = $fs->readStream('uploads/large.zip');    // resource
```

### Directory Operations
```php
$fs->createDirectory('path/to/dir');
$fs->deleteDirectory('path/to/dir');               // recursive
foreach ($fs->listContents('uploads', recursive: false) as $item) {
    $item->path(); $item->size(); $item->lastModified(); $item->isFile(); $item->isDirectory();
}
```

### Visibility (concrete `Filesystem` only — not in Contract)
```php
$fs->setVisibility('file.txt', 'public');   // Local: chmod, S3: ACL
$fs->setVisibility('file.txt', 'private');
$visibility = $fs->getVisibility('file.txt');  // 'public'|'private'
```

## CONFIG

### LocalConfig (readonly VO)
```php
new LocalConfig(
    root: '/var/app/storage',    // required — validated via realpath(), throws FilesystemException if missing
    filePermissions: 0644,       // new files
    dirPermissions: 0755,        // new directories
    followSymlinks: true,
    publicFilePerms: 0644,
    privateFilePerms: 0600,
    publicDirPerms: 0755,
    privateDirPerms: 0700,
)
```

### S3Config (readonly VO)
```php
new S3Config(
    bucket: 'my-bucket',                  // required
    region: 'eu-central-1',              // required
    key: 'AKIAEXAMPLE',                  // required
    secret: 'secret',                    // required — #[SensitiveParameter] + __debugInfo()
    endpoint: 'https://s3.amazonaws.com', // default AWS; override for MinIO/DO Spaces
    prefix: '',                           // path prefix in bucket
)
```

## CONTRACTS
| Interface | Methods |
|-----------|---------|
| `FilesystemServiceInterface` | `local(root)`, `s3(bucket, region, key, secret, endpoint?, prefix?)` |
| `FilesystemInterface` | extends Reader + Writer |
| `FilesystemReaderInterface` | `read`, `readStream`, `exists`, `size`, `lastModified`, `mimeType`, `listContents` |
| `FilesystemWriterInterface` | `write`, `writeStream`, `delete`, `copy`, `move`, `createDirectory`, `deleteDirectory` |
| `FileInfoInterface` | `path`, `size`, `lastModified`, `isFile`, `isDirectory` |
| `FilesystemExceptionInterface` | extends `Throwable` |

All in `JardisSupport\Contract\Filesystem\*`. Split Reader/Writer enables read-only injection:
```php
public function __construct(private readonly FilesystemReaderInterface $storage) {}
```

## EXCEPTIONS
All implement `FilesystemExceptionInterface`.

| Exception | Trigger |
|-----------|---------|
| `FilesystemException` | Base — path traversal, null byte, invalid config |
| `FileNotFoundException` | File/directory does not exist |
| `FileExistsException` | Defined, never thrown — `grep -rn "FileExistsException" src/ tests/` finds only the class declaration (`src/Exception/FileExistsException.php:10`), 0 `throw` sites |
| `UnableToReadException` | Read error (permissions, I/O, S3 auth) |
| `UnableToWriteException` | Write error (permissions, disk, S3) |
| `UnableToDeleteException` | Delete error |

## SECURITY
- Path traversal: `..` and null bytes rejected by `PathNormalizer`
- Symlink escape: `LocalFullPath` checks `realpath()` containment within root
- Root validation: `LocalConfig` resolves root via `realpath()` in constructor
- XXE: `LIBXML_NONET` on all `simplexml_load_string` calls (S3)
- Secret masking: `S3Config::$secret` has `#[\SensitiveParameter]` + `__debugInfo()`
- Bucket wipe guard: empty prefix on `deleteDirectory` is forbidden (S3)

## ENV VARIABLES
```env
FS_LOCAL_ROOT=/var/app/storage
FS_LOCAL_PERMISSIONS_FILE=0644
FS_LOCAL_PERMISSIONS_DIR=0755
FS_S3_BUCKET=my-bucket
FS_S3_REGION=eu-central-1
FS_S3_KEY=AKIAEXAMPLE
FS_S3_SECRET=secret
FS_S3_ENDPOINT=https://s3.amazonaws.com
FS_S3_PREFIX=uploads/
```
## KERNEL INTEGRATION
Access: `$kernel->filesystem(): ?FilesystemServiceInterface` — s. Skill core-kernel.
Returns the instance or `null` — there is no third state; the accessor is typed
`?FilesystemServiceInterface`, so `false` is impossible. `null` means: adapter not installed.

**The kernel reads no ENV for this adapter.** The `FS_*`/`FS_S3_*` variables above are read by the
developer, who builds the Config objects and passes them to `FilesystemService`.

## RULES
- Never instantiate Handlers directly — always via `FilesystemService` + Config
- Multiple `Filesystem` instances per project are the norm (different storage backends)
- `setVisibility`/`getVisibility` only on concrete `Filesystem`, not via `FilesystemInterface`

## DEPENDENCIES
| Package | Purpose |
|---------|---------|
| `jardissupport/contracts ^1.0` | Filesystem interfaces |
| `ext-curl` | S3 API calls |
| `ext-fileinfo` | MIME type detection (local) |
| `ext-simplexml` | S3 XML response parsing |
