# Models

NoClass™ PHP Procedural Framework  
Copyright 2024-2026 Danny Mbanginu.

Licensed under the Apache License, Version 2.0.  
See the project `LICENSE` file for details.

## Purpose

This folder is for application model files.

Models should contain procedural functions that handle data access and business logic.

Example naming style:

```php
user_getAll();
user_findById($id);
blog_getRecent();
```

## Default Demo Note

The default NoClass™ demo does not include database calls.

This is intentional so that new users can run the framework immediately without creating a database or editing database credentials.

Database examples should live in separate documentation or example applications.
