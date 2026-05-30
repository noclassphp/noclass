# NoClass™ PHP Procedural Framework

NoClass is an open-source procedural PHP MVC framework designed for developers who want structure without user-defined classes.

It provides routing, controllers, models, middleware, HMVC modules, security features, database helpers, asset management, and modern development tools while maintaining a procedural programming approach.

> Build structured PHP applications without classes.

Official Website: https://noclass.org

Official GitHub Repository: https://github.com/noclassphp/noclass

---

NoClass is designed to adapt to your hosting environment.

The framework supports multiple deployment structures, including traditional shared hosting layouts, single-folder deployments, and production-grade deployments where application code is stored outside the public document root.

This flexibility allows developers to start small and scale their deployment architecture as their projects grow, without needing to migrate to a different framework or rewrite application code.

# Why NoClass?

Most modern PHP frameworks are built around:

* Classes
* Service containers
* Dependency injection
* Facades
* Complex bootstrapping
* Heavy abstraction layers

These approaches can be powerful, but they also introduce complexity that is not always necessary.

NoClass takes a different approach.

It keeps the useful structure of an MVC framework while allowing developers to build applications using procedural PHP functions.

The result is a framework that is:

* Easy to learn
* Easy to debug
* Easy to understand
* Easy to extend
* Lightweight
* Practical for real-world projects

NoClass is ideal for developers who prefer procedural programming while still wanting modern framework features.

---

# Lightweight Core

NoClass is intentionally lightweight.

Approximate framework size:

| Package                                        | Source Size | On Disk |
| ---------------------------------------------- | ----------- | ------- |
| Full Framework                                 | 340 KB      | 446 KB  |
| Framework Only (excluding legal documentation) | 276 KB      | 356 KB  |

The small footprint makes the framework:

* Easier to audit
* Easier to understand
* Easier to deploy
* Easier to maintain

Unlike many modern frameworks, developers can read and understand the entire framework source code in a relatively short period of time.

---

# Designed for Performance

NoClass follows a lightweight procedural architecture.

Key design decisions include:

* No dependency injection container
* No service provider bootstrapping
* No user-defined classes in normal application flow
* Minimal framework abstraction
* Direct function calls
* Simple request lifecycle

This design aims to reduce framework overhead and keep applications efficient.

Formal benchmarking against other frameworks is planned for future releases.

---

# Features

NoClass includes a growing collection of procedural framework features.

## MVC Architecture

* Function-based controllers
* Function-based models
* View rendering
* Layout support
* Data passing helpers
* MVC project structure

## HMVC Module Support

* Modular applications
* Module controllers
* Module models
* Module views
* Independent feature modules
* Large application organisation

## Routing

* Dynamic routing
* Route configuration
* URI segment routing
* Route helper functions
* Clean URL support

## Database Layer

NoClass includes a lightweight database abstraction layer with ORM-style helper functions.

Available helpers include:

```php
db_connect();
db_select();
db_insert();
db_update();
db_delete();
db_batch_insert();
db_raw();
db_raw_secure();
```

Features include:

* Secure parameter binding
* CRUD operations
* Batch inserts
* Raw SQL support
* Transaction support
* Lightweight abstraction layer

## Security

Built-in security features include:

* CSRF protection
* Content Security Policy (CSP)
* CSP nonce generation
* Security headers
* XSS protection helpers
* Input sanitisation helpers
* Security event logging
* Session security helpers

## Asset Management

Built-in asset helpers include:

* Asset URL generation
* Secure asset generation
* Asset versioning
* Cache busting
* CDN support
* Automatic URL generation

Example:

```php
asset('css/app.css');

secure_asset('js/app.js');
```

## Frontend Helpers

NoClass includes frontend development helpers such as:

* Grid.js integration
* HTTP/AJAX helper library
* Form helpers
* JavaScript utility functions
* Asset loading helpers

## Middleware

* Function-based middleware
* Route protection
* Authentication checks
* Logging middleware
* Security middleware
* Request filtering

## Development Features

* Environment configuration
* Error handling
* Logging
* Composer support
* Third-party library integration
* Configuration management
* Debugging helpers

---

# Core Philosophy

NoClass is not anti-structure.

NoClass is anti-unnecessary complexity.

The framework is built around a simple idea:

Structured PHP applications do not require user-defined classes.

Controllers, models, middleware, helpers, and modules can all be built using procedural PHP functions while still maintaining a clean and organised codebase.

NoClass aims to make PHP development:

* Easier to learn
* Easier to understand
* Easier to debug
* Easier to maintain

without sacrificing practical functionality.

---

# Key Principles

* Procedural PHP first
* MVC architecture
* Function-based controllers
* Function-based models
* Function-based middleware
* Lightweight core
* Minimal magic
* Readable code
* Practical development workflow
* Real-world usability

---

# What NoClass Is

NoClass is:

* A procedural PHP framework
* An MVC framework
* A lightweight application structure
* A routing system
* A rendering system
* A helper-based framework
* A modular framework
* A practical alternative to class-heavy PHP frameworks

---

# What NoClass Is Not

NoClass is not:

* A Laravel clone
* A Symfony clone
* A class-based framework
* A dependency injection framework
* A service-container framework
* A framework that forces object-oriented programming

NoClass may still work with third-party libraries that use classes.

The framework itself, however, is designed around procedural PHP.

---

# Project Status

NoClass is under active development.

The framework has reached a mature and usable stage suitable for:

* Learning projects
* Internal business systems
* Dashboards
* Administrative systems
* APIs
* Modular applications
* Lightweight production applications

While development continues, the framework already provides a stable procedural MVC foundation for practical development work.

# Project Structure

NoClass supports multiple deployment structures to accommodate different hosting environments.

The framework can be deployed on shared hosting, VPS servers, dedicated servers, and custom hosting environments while maintaining the same procedural MVC architecture.

---

## Option 1: Legacy Hosting Structure

This structure is suitable for older hosting environments where all application files reside in a single web-accessible directory.

```text
NoClass/
├── noclass_app/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   ├── middleware/
│   ├── modules/
│   ├── lib/
│   └── system/
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── ...
│
├── index.php
├── .htaccess
├── LICENSE
├── NOTICE
├── README.md
└── ...
```

This approach provides maximum compatibility with older hosting environments and simple deployments.

While fully supported, it offers less separation between public assets and application files than the recommended production structure.

---

## Option 2: Recommended Production Structure

This is the recommended deployment structure whenever your hosting provider allows you to configure the document root.

```text
project-root/
├── noclass_app/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   ├── middleware/
│   ├── modules/
│   ├── lib/
│   └── system/
│
├── public/
│   ├── assets/
│   ├── .htaccess
│   └── index.php
│
├── LICENSE
├── NOTICE
├── CONTRIBUTING.md
├── SECURITY.md
├── TRADEMARK.md
├── README.md
└── ...
```

Some hosting providers may use:

```text
public_html/
```

instead of:

```text
public/
```

In this structure:

* Only the contents of `public/` (or `public_html/`) are accessible from the web.
* Application code remains outside the document root.
* Configuration files are protected.
* Models, controllers, middleware, and system files are not directly accessible.

This structure provides the highest level of security and is recommended for production deployments.

---

## Option 3: Default NoClass Structure

This is the default structure included with NoClass.

It is commonly used on shared hosting environments where the application resides inside a single directory.

```text
NoClass/
├── noclass_app/
│   ├── config/
│   │   ├── config.php
│   │   ├── database.php
│   │   ├── routes.php
│   │   └── services.php
│   │
│   ├── controllers/
│   │   ├── Home.php
│   │   ├── Blog.php
│   │   └── User.php
│   │
│   ├── models/
│   │   ├── Blog.php
│   │   └── User.php
│   │
│   ├── views/
│   │   ├── home/
│   │   ├── blog/
│   │   └── layouts/
│   │
│   ├── middleware/
│   │   └── Auth.php
│   │
│   ├── modules/
│   │   └── blog/
│   │       ├── controllers/
│   │       ├── models/
│   │       └── views/
│   │
│   ├── lib/
│   │   ├── integrations/
│   │   ├── email.php
│   │   └── security.php
│   │
│   └── system/
│       ├── Route.php
│       ├── db.php
│       ├── func.php
│       ├── Respond.php
│       ├── security.php
│       └── ...
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── ...
│
├── index.php
├── .htaccess
├── LICENSE
├── NOTICE
├── CONTRIBUTING.md
├── SECURITY.md
├── TRADEMARK.md
├── README.md
└── ...
```

NoClass includes server-level protections to prevent direct access to application files when properly configured.

This structure provides a good balance between simplicity, compatibility, and security and works well on most shared hosting providers.

---

## Composer Support

NoClass does not include a `vendor/` directory by default.

If Composer packages are installed, Composer will create the directory automatically:

```text
vendor/
```

NoClass remains fully functional without Composer and does not require Composer for core framework operation.

---

## Directory Overview

### noclass_app/

Contains the application and framework code.

Subdirectories include:

* config/
* controllers/
* models/
* views/
* middleware/
* modules/
* lib/
* system/

### assets/

Contains publicly accessible assets such as:

* CSS
* JavaScript
* Images
* Fonts
* Media files

### index.php

The application entry point.

All requests are routed through this file.

### .htaccess

Apache rewrite and security configuration.

Used to route requests and protect framework resources.

---

The exact structure may vary depending on deployment requirements, but the procedural MVC architecture and NoClass philosophy remain the same across all supported layouts.

This layout provides a good balance between compatibility and security and works well on most shared hosting environments.

The structure may evolve between releases, but the overall MVC and procedural philosophy remains consistent.

---

# Installation

## Requirements

Minimum supported environment:

* PHP 7.4.33 or newer
* Apache, Nginx, or a compatible web server
* MySQL or MariaDB
* Composer (optional)
* HTTPS recommended

## Clone the Repository

```bash
git clone https://github.com/noclassphp/noclass.git
```

Or download the latest release package from GitHub.

## Configure the Application

Update the configuration files:

```text
app/config/config.php
app/config/database.php
app/config/routes.php
```

Configure:

* Website URL
* Database credentials
* Environment settings
* Security settings

## Web Server Configuration

### Recommended Structure

If using the recommended structure, point your web server document root to:

```text
public/
```

### Default Structure

If using the default NoClass structure on shared hosting, ensure that application directories are protected from direct access using the provided server configuration files.

NoClass includes a default `.htaccess` configuration for Apache installations.

For custom server configurations, ensure all requests are routed through `index.php`.

Example Apache configuration:

```apache
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

---

# Routing

NoClass uses a simple routing approach.

The default route format is:

```text
/controller/action/param1/param2
```

Example:

```text
/home/index
```

Loads:

```text
controllers/Home.php
```

And executes:

```php
index();
```

Example:

```text
/blog/post/my-first-post
```

May execute:

```php
post('my-first-post');
```

Routes can also be defined manually in:

```text
app/config/routes.php
```

Example:

```php
return [

    '/' => [
        'controller' => 'Home',
        'action'     => 'index'
    ],

    '/about' => [
        'controller' => 'Home',
        'action'     => 'about'
    ]

];
```

This allows clean and maintainable route definitions.

---

# Controllers

Controllers are procedural PHP files.

A controller receives requests, coordinates application logic, and prepares data for the view.

Example:

```php
<?php

function index()
{
    data('title', 'Welcome to NoClass');
}
```

Controller files reside in:

```text
app/controllers/
```

Example:

```text
app/controllers/Home.php
```

By default, NoClass automatically loads the corresponding view based on the controller and action being executed, so explicit view rendering is typically unnecessary.

Unlike class-based frameworks, controllers contain simple functions rather than controller classes.

---

# Models

Models contain business logic and database operations.

Example:

```php
<?php

function blog_getAll()
{
    return db_select(
        'posts',
        '*',
        [],
        'created_at DESC'
    );
}

function blog_findBySlug($slug)
{
    return db_select(
        'posts',
        '*',
        ['slug' => $slug],
        '',
        '1'
    );
}
```

Models reside in:

```text
app/models/
```

Example:

```text
app/models/Blog.php
```

The recommended naming convention is:

```php
blog_getAll();
blog_findBySlug();
user_findById();
user_getRecent();
```

This helps avoid naming collisions while keeping functions readable.

---

# Views

Views are responsible for presentation.

Views typically contain:

* HTML
* CSS
* JavaScript
* Minimal PHP output logic

Example:

```php
<h1><?= e(data('title')) ?></h1>

<ul>
<?php foreach (data('posts') as $post): ?>

    <li>
        <?= e($post['title']) ?>
    </li>

<?php endforeach; ?>
</ul>
```

Views reside in:

```text
app/views/
```

Example:

```text
app/views/home/index.php
```

Views should avoid complex business logic.

Business logic belongs in models and controllers.

---

# Layouts

Layouts provide reusable page structures.

Example:

```php
<!DOCTYPE html>
<html>
<head>
    <title><?= e(data('title')) ?></title>
</head>
<body>

    <?= content() ?>

</body>
</html>
```

A layout may contain:

* Header
* Footer
* Navigation
* Sidebar
* Scripts
* Stylesheets

Layouts help maintain a consistent application design.

---

# Passing Data Between Controllers and Views

NoClass includes helper functions for controller-to-view communication.

Example:

```php
data('title', 'Dashboard');

data('users', $users);
```

Inside the view:

```php
<h1><?= e(data('title')) ?></h1>
```

```php
<?php foreach (data('users') as $user): ?>
    <?= e($user['name']) ?>
<?php endforeach; ?>
```

This provides a simple and readable alternative to complex view models or dependency injection systems.

---

# Responses

NoClass includes response helper functions for returning output.

Examples include:

```php
successResponse();
errorResponse();
jsonResponse();
```

These helpers make it easier to return:

* JSON responses
* API responses
* Success messages
* Error messages

while maintaining a consistent structure throughout the application.

---

# Error Handling

NoClass includes built-in error handling facilities.

Features include:

* Error pages
* Logging
* Development error output
* Production-safe responses
* Exception handling support

The framework aims to provide useful debugging information during development while protecting sensitive information in production environments.

---

# Logging

NoClass includes logging functionality for:

* Application events
* Security events
* Errors
* Debugging information

Logging can be useful for:

* Troubleshooting
* Auditing
* Monitoring
* Security investigations

Developers should avoid logging sensitive information such as passwords, tokens, or private credentials.

# Middleware

NoClass supports function-based middleware.

Middleware allows code to execute before or after a route is processed.

Common middleware use cases include:

* Authentication
* Authorisation
* Rate limiting
* Request filtering
* Maintenance mode
* Security checks
* Logging
* API protection

Example:

```php
function auth_middleware()
{
    if (!session('user_id')) {
        redirect('/login');
    }
}
```

Middleware files are typically stored in:

```text
app/middleware/
```

Example:

```text
app/middleware/Auth.php
```

Unlike many frameworks, middleware remains procedural and easy to follow.

---

# HMVC Modules

As applications grow, organising everything under a single controllers and models folder can become difficult.

NoClass supports HMVC-style modules to help organise larger applications.

Example structure:

```text
modules/
└── blog/
    ├── controllers/
    │   └── Blog.php
    ├── models/
    │   └── Blog.php
    └── views/
        ├── index.php
        └── post.php
```

Each module can contain:

* Controllers
* Models
* Views
* Assets
* Middleware

This allows applications to be organised by feature rather than by technical layer.

Benefits include:

* Better maintainability
* Easier scaling
* Improved separation of concerns
* Cleaner large applications

---

# Database Helpers

NoClass includes a lightweight procedural database abstraction layer.

The goal is to simplify common database operations without introducing the complexity of a traditional ORM.

Available helpers include:

```php
db_connect();
db_select();
db_insert();
db_update();
db_update1();
db_delete();
db_batch_insert();
db_raw();
db_raw_secure();
```

## Selecting Records

Example:

```php
$users = db_select('users');
```

Specific columns:

```php
$users = db_select(
    'users',
    'id,name,email'
);
```

Using conditions:

```php
$user = db_select(
    'users',
    '*',
    [
        'id' => 1
    ]
);
```

## Inserting Records

Example:

```php
db_insert('users', [
    'name'  => 'John',
    'email' => 'john@example.com'
]);
```

## Updating Records

Example:

```php
db_update(
    'users',
    [
        'name' => 'Updated Name'
    ],
    [
        'id' => 1
    ]
);
```

## Deleting Records

Example:

```php
db_delete(
    'users',
    [
        'id' => 1
    ]
);
```

## Raw Queries

Example:

```php
$result = db_raw_secure(
    "SELECT * FROM users WHERE email = ?",
    $email
);
```

This provides flexibility while maintaining secure parameter binding.

---

# Security

Security is a core focus of NoClass.

The framework includes a growing collection of security-related helpers and protections.

## CSRF Protection

Cross-Site Request Forgery protection helps prevent unauthorised form submissions.

Example:

```php
csrf_field();
```

Within forms:

```php
<form method="post">

    <?= csrf_field() ?>

</form>
```

Validation:

```php
csrf_validate();
```

---

## Content Security Policy (CSP)

NoClass includes support for Content Security Policy headers.

Benefits include:

* Reduced XSS attack surface
* Better browser protection
* Improved frontend security

Example:

```php
generate_csp_header();
```

---

## CSP Nonces

NoClass supports CSP nonces for inline scripts and styles.

Generate a nonce:

```php
$nonce = csp_nonce();
```

Usage:

```php
<script nonce="<?= e(csp_nonce()) ?>">
    console.log('Secure inline script');
</script>
```

This allows secure use of inline JavaScript when CSP is enabled.

---

## Output Escaping

Always escape user-controlled output.

Example:

```php
<?= e($user['name']) ?>
```

Benefits:

* Prevents XSS attacks
* Improves output safety
* Encourages secure coding practices

---

## Security Event Logging

Security-related events can be logged for auditing and monitoring.

Examples:

* Failed logins
* CSRF failures
* Suspicious requests
* Access violations

This assists with security investigations and compliance requirements.

---

# Asset Management

NoClass includes asset helpers for generating asset URLs.

Example:

```php
asset('css/app.css');
```

Output:

```text
https://example.com/assets/css/app.css
```

---

## Secure Assets

Example:

```php
secure_asset('js/app.js');
```

Useful when enforcing HTTPS resources.

---

## Asset Versioning

Asset versioning can help prevent browser caching issues.

Example output:

```text
/app.css?v=1.0.0
```

Benefits include:

* Cache busting
* Faster deployments
* Easier updates

---

## CDN Support

NoClass supports asset delivery through CDNs.

Example configuration:

```php
define('CDN_URL', 'https://cdn.example.com');
```

Assets will automatically use the configured CDN URL.

---

# Frontend Development

NoClass includes frontend helper support to simplify modern web development.

Features include:

* JavaScript helpers
* HTTP utilities
* AJAX support
* Asset management
* Grid.js integration

---

# HTTP.js

NoClass includes a lightweight HTTP helper library.

The goal is to simplify:

* GET requests
* POST requests
* AJAX calls
* API communication

Example:

```javascript
const response = await http.post(
    '/users/create',
    {
        name: 'John'
    }
);
```

Benefits:

* Cleaner code
* Consistent requests
* Reduced boilerplate

---

# Grid.js Integration

NoClass includes helper support for Grid.js tables.

Example:

```php
table_init_gridjs('#users-table');
```

Benefits:

* Searchable tables
* Pagination
* Sorting
* Modern UI
* Minimal configuration

Grid.js support is optional and can be removed if not required.

---

# Forms

NoClass includes form-related helper functionality.

Common uses include:

* CSRF tokens
* Validation
* Sanitisation
* AJAX submission

The framework encourages secure form handling practices by default.

---

# Configuration

Application configuration is stored in:

```text
app/config/
```

Common configuration files include:

```text
config.php
database.php
routes.php
services.php
```

Keeping configuration separate helps maintain clean application code.

---

# Services

NoClass supports optional service integrations through:

```text
app/config/services.php
```

Examples may include:

* SMTP
* External APIs
* Payment gateways
* Third-party integrations

The framework itself remains procedural while allowing integration with external libraries.

---

# Third-Party Libraries

NoClass supports Composer and third-party packages.

Example:

```bash
composer require vendor/package
```

Although NoClass avoids user-defined classes within application flow, it remains fully compatible with external packages that use object-oriented code.

This allows developers to combine NoClass simplicity with the broader PHP ecosystem.

---

# Integrations

The framework includes:

```text
app/lib/integrations/
```

This directory is intended for:

* SMTP integrations
* Payment gateways
* Messaging services
* External APIs
* Custom integrations

Developers are free to implement integrations using either procedural code or vendor packages where appropriate.

# Development Workflow

A typical NoClass application follows a simple and predictable development workflow.

## Step 1: Define a Route

Example:

```php
return [

    '/blog' => [
        'controller' => 'Blog',
        'action' => 'index'
    ]

];
```

## Step 2: Create a Controller

```php
<?php

function index()
{
    $posts = blog_getAll();

    data('posts', $posts);

    render_view('blog/index');
}
```

## Step 3: Create a Model

```php
<?php

function blog_getAll()
{
    return db_select(
        'posts',
        '*',
        [],
        'created_at DESC'
    );
}
```

## Step 4: Create a View

```php
<h1>Blog Posts</h1>

<?php foreach (data('posts') as $post): ?>

    <p><?= e($post['title']) ?></p>

<?php endforeach; ?>
```

The framework handles the rest.

This simple workflow makes NoClass easy to understand and easy to teach.

---

# Example Request Lifecycle

Understanding how a request flows through the framework is straightforward.

```text
Browser Request
        │
        ▼
     Route
        │
        ▼
  Controller
        │
        ▼
     Model
        │
        ▼
   Database
        │
        ▼
     View
        │
        ▼
    Response
```

For example:

```text
/blog/post/my-first-post
```

May execute:

```text
Route
 → Blog Controller
 → Blog Model
 → Database Query
 → View Rendering
 → HTML Response
```

There are no controller classes, service containers, or complex dependency graphs to trace.

---

# Naming Conventions

NoClass follows simple procedural naming conventions.

Recommended:

```php
user_getAll();
user_findById();
user_create();
user_update();

blog_getRecent();
blog_findBySlug();

auth_login();
auth_logout();
```

Avoid:

```php
User_getAll();
User_FindById();

Blog_GetRecent();
```

Lowercase function names are preferred for consistency and readability.

---

# Best Practices

The following practices are recommended when developing with NoClass.

## Keep Controllers Thin

Controllers should:

* Receive requests
* Call models
* Prepare view data
* Return responses

Avoid placing large amounts of business logic inside controllers.

---

## Keep Models Focused

Models should contain:

* Database operations
* Business rules
* Data transformation logic

Keeping models focused improves maintainability.

---

## Escape Output

Always escape user-controlled output.

Good:

```php
<?= e($user['name']) ?>
```

Avoid:

```php
<?= $user['name'] ?>
```

Escaping output helps prevent XSS vulnerabilities.

---

## Use Database Helpers

Prefer framework database helpers:

```php
db_select();
db_insert();
db_update();
db_delete();
```

instead of constructing unsafe SQL manually.

---

## Use Middleware

Protect sensitive routes using middleware.

Examples:

* Admin areas
* API endpoints
* Account settings
* Payment workflows

Middleware helps centralise security and access control logic.

---

## Organise Large Projects with Modules

When applications grow, use modules.

Benefits:

* Better organisation
* Easier maintenance
* Cleaner codebases
* Team scalability

---

# Why Choose NoClass?

NoClass offers a unique combination of simplicity and functionality.

## Procedural First

Develop applications using functions instead of user-defined classes.

## Lightweight

Approximately:

* 303 KB source size (404 KB on disk)
* 261 KB source size (344 KB on disk) excluding legal documentation

## MVC Structure

Maintain clean application organisation without unnecessary complexity.

## HMVC Support

Organise larger applications into independent modules.

## Security Features

Includes:

* CSRF protection
* CSP support
* CSP nonces
* Secure headers
* Security logging

## Modern Features

Includes:

* Asset management
* HTTP helper library
* Grid.js integration
* Middleware support
* Composer compatibility

## Easy Learning Curve

Developers familiar with procedural PHP can become productive quickly.

---

# Who Is NoClass For?

NoClass is particularly suitable for:

* Procedural PHP developers
* Students learning MVC architecture
* Developers migrating from plain PHP
* Small business applications
* Internal systems
* Dashboards
* Administrative portals
* APIs
* Modular applications
* Educational environments

---

# Roadmap

Future enhancements may include:

* Expanded documentation
* Starter applications
* Example blog project
* Example admin panel
* Additional middleware helpers
* Validation helpers
* CLI tooling
* Testing examples
* Extended API tooling
* Additional frontend utilities
* Performance benchmarking
* Expanded module tooling

The roadmap may evolve as the project grows.

---

# Contributing

Contributions are welcome.

However, contributions should respect the core philosophy of the project.

NoClass is intentionally procedural.

Contributions should not attempt to replace the framework's function-based architecture with a class-based architecture.

Before contributing:

1. Read CONTRIBUTING.md
2. Review project coding standards
3. Follow existing naming conventions
4. Preserve procedural design principles

Community discussions, issues, and pull requests are available through GitHub.

---

# Commercial Ecosystem

The NoClass™ Framework core is open-source software licensed under the Apache License, Version 2.0.

In addition to the open-source framework, the NoClass™ project may offer optional commercial products and services, including:

* Premium modules
* Enterprise libraries
* Starter applications
* Professional support
* Consulting services
* Training programmes
* Hosted services
* Commercial integrations

Commercial offerings, where available, may be distributed under separate licences and terms.

The availability of commercial products does not affect the licensing of the NoClass™ Framework core.

The framework itself remains available under the Apache License, Version 2.0.

---

# Licence

NoClass™ PHP Procedural Framework is licensed under the Apache License, Version 2.0.

See:

```text
LICENSE
```

for the complete licence text.

---

# Notice

See:

```text
NOTICE
```

for copyright, attribution, and project identity information.

---

# Trademark

NoClass™ and associated branding are trademarks of Danny Mbanginu.

The Apache License covers the source code.

The Apache License does not grant unrestricted rights to use:

* The NoClass™ name
* The NoClass™ logo
* Project branding
* Associated trademarks

See:

```text
TRADEMARK.md
```

for complete trademark information.

---

# Security

Security vulnerabilities should be reported privately.

Please refer to:

```text
SECURITY.md
```

for reporting procedures.

Do not disclose security vulnerabilities publicly before they have been reviewed and addressed.

---

# Third-Party Software

NoClass may be used with third-party packages and Composer libraries.

Some third-party libraries may use object-oriented code.

This does not conflict with the procedural philosophy of NoClass.

The framework itself remains procedural while maintaining compatibility with the wider PHP ecosystem.

---

# Authorship

NoClass™ was originally created by Danny Mbanginu in 2024.

The project continues to be developed as a procedural PHP MVC framework for developers who want structure without user-defined classes.

Official Website:

```text
https://noclass.org
```

Official GitHub Repository:

```text
https://github.com/noclassphp/noclass
```

---

# Copyright

```text
Copyright 2024-2026 Danny Mbanginu
```

---

# Final Thoughts

NoClass is built around a simple idea:

> Structured PHP applications do not require user-defined classes.

By combining procedural PHP with MVC architecture, modular organisation, security features, database helpers, and modern development tools, NoClass provides a practical alternative for developers who prefer a more direct and lightweight development experience.

Whether you are building a small internal tool, a business application, a dashboard, an API, or a large modular system, NoClass aims to provide the structure you need without the complexity you do not.
