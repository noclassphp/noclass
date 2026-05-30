# NoClass Integrations

Copyright © 2024-2026 Danny Mbanginu

NoClass™ PHP Procedural Framework

Licensed under the Apache License, Version 2.0.

---

## Purpose

The `lib/integrations/` directory contains optional integrations with external services, APIs, SDKs, and third-party systems.

Examples include:

* SMTP providers
* Payment gateways
* Redis
* Search engines
* Cloud storage providers
* SMS providers
* Analytics services
* External APIs

---

## Directory Structure

Example:

```text
lib/
└── integrations/
    ├── smtp.php
    ├── stripe.php
    ├── paypal.php
    ├── redis.php
    └── README.md
```

---

## Configuration

Integration configuration should be stored in:

```text
config/services.php
```

Example:

```php
'smtp' => [
    'host' => getenv('SMTP_HOST') ?: '',
]
```

Environment-specific values should be stored in:

```text
.env
```

or

```text
.env.example
```

---

## Composer Packages

Some integrations may require third-party Composer packages.

These packages are not included with NoClass by default.

Developers are free to install and configure any compatible packages required by their project.

Example:

```bash
composer require phpmailer/phpmailer
```

---

## NoClass Philosophy

NoClass does not require any specific external service.

Integrations are optional.

Applications should only install and configure the services they actually need.

This helps keep NoClass lightweight, portable, and easy to understand.
