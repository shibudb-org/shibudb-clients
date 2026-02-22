# ShibuDb Clients

Multi-language client libraries for [ShibuDb](https://github.com/Podcopic-Labs/ShibuDb) database.

## Clients

| Client | Directory | Requirements |
|--------|-----------|--------------|
| **Python** | [python/](python/) | Python 3.7+ |
| **PHP** | [php/](php/) | PHP 5.6+ |

## Quick Start

### Python

```bash
cd python
pip install .
# or: cp shibudb_client.py your_project/
```

```python
from shibudb_client import ShibuDbClient
client = ShibuDbClient("localhost", 4444)
client.authenticate("admin", "admin")
```

See [python/README.md](python/README.md) for full documentation.

### PHP

```bash
cp php/ShibuDbClient.php /path/to/your/project/
```

```php
<?php
require_once 'ShibuDbClient.php';
$client = new ShibuDbClient('localhost', 4444);
$client->authenticate('admin', 'admin');
```

See [php/README.md](php/README.md) for full documentation.

## Prerequisites

Ensure the ShibuDb server is running:

```bash
sudo shibudb start 4444
```

## Running Tests

### Python

```bash
cd python
python simple_test.py
python pool_test.py
python comprehensive_pool_test.py
python json_parsing_test.py
python special_chars_test.py
```

### PHP

```bash
cd php
php simple_test.php
php pool_test.php
php comprehensive_pool_test.php
php json_parsing_test.php
php special_chars_test.php
```

## License

See [LICENSE](LICENSE).
