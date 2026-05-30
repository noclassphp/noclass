
# `CONTRIBUTING.md`


# Contributing to NoClass™

Thank you for your interest in contributing to NoClass™.

NoClass™ is a procedural PHP MVC framework built around a simple principle:

> Build structured PHP applications without user-defined classes.

The framework is designed for developers who prefer procedural PHP while still
wanting clear routing, controllers, models, views, middleware, modules, and
reusable system functions.

## 1. Project Philosophy

NoClass™ intentionally avoids user-defined PHP classes in the framework core
and application structure.

Contributions should respect the following principles:

- Use procedural PHP.
- Do not introduce framework-level user-defined classes.
- Keep functions simple, readable, and predictable.
- Prefer clear naming over clever abstraction.
- Preserve the MVC structure.
- Keep the framework lightweight.
- Avoid unnecessary dependencies.
- Maintain compatibility with the existing NoClass™ routing and helper patterns.
- Follow the existing lowercase procedural naming style.

Example naming style:

```php
user_getAll();
blog_findBySlug();
module_model();
render_view();
route();

NoClass™ may still load third-party libraries that use classes through Composer
or spl_autoload_register, but the NoClass™ framework itself should remain
procedural.
```
## 2. Licence of Contributions

By contributing to NoClass™, you agree that your contributions will be licensed
under the Apache License, Version 2.0.

Unless you clearly state otherwise in writing before your contribution is
accepted, any code, documentation, tests, examples, or other materials you
intentionally submit to NoClass™ will be treated as a contribution under the
Apache License, Version 2.0.

This means your contribution may be used, modified, distributed, sublicensed,
and included in future versions of NoClass™ under the Apache License, Version 2.0.

## 3. Copyright

The project copyright notice is:

Copyright 2024-2026 Danny Mbanginu

Contributors retain copyright in their own contributions where applicable, while
granting the project and its users the rights provided by the Apache License,
Version 2.0.

## 4. Trademark

NoClass™ is a trademark of Danny Mbanginu.

The Apache License, Version 2.0 covers the NoClass™ source code, but it does not
grant unrestricted rights to use the NoClass™ name, logo, or branding.

Please see TRADEMARK.md for the rules governing use of the NoClass™ name and
identity.

## 5. What You Can Contribute

Useful contributions include:

Bug fixes
Documentation improvements
Examples and tutorials
Tests
Routing improvements
Middleware improvements
Module/HMVC improvements
Security improvements
Performance improvements
Compatibility fixes
Developer experience improvements
Error handling improvements

Before working on a major feature, please open an issue first so the idea can
be discussed.

## 6. What May Not Be Accepted

A contribution may be rejected if it:

Introduces user-defined classes into the NoClass™ framework core.
Replaces the procedural architecture with an object-oriented architecture.
Adds unnecessary complexity.
Breaks existing routing behaviour without a strong reason.
Introduces large dependencies without discussion.
Changes the project philosophy.
Reduces security.
Makes the framework harder for beginners to understand.
Lacks clear documentation where documentation is needed.

## 7. Coding Style

Please follow these general coding rules:

Use clear procedural function names.
Use lowercase function prefixes where appropriate.
Keep files focused on their purpose.
Validate and sanitize input.
Avoid global state unless it follows existing NoClass™ conventions.
Avoid hidden side effects.
Prefer explicit behaviour.
Keep error messages useful but safe.
Do not expose sensitive server, database, or path information to users.

Example function naming style:

blog_getAll();
blog_findById($id);
user_findByEmail($email);
auth_check();
security_token();

## 8. PHP Compatibility

Contributions should state the PHP version they were tested with.

Avoid using PHP features that would unnecessarily raise the minimum PHP version
unless the project has agreed to do so.

If a feature requires a newer PHP version, explain why.

## 9. Security

Security-related changes are welcome, but please be careful when submitting
public issues or pull requests involving vulnerabilities.

If you believe you have found a security vulnerability, do not open a public
GitHub issue.

Please follow the process in SECURITY.md.

## 10. Pull Request Guidelines

When submitting a pull request:

Explain what the change does.
Explain why the change is needed.
Mention any related issue.
Include examples where useful.
Include tests where practical.
Update documentation if behaviour changes.
Keep the pull request focused.

A good pull request should be easy to review.

## 11. Documentation Guidelines

Documentation should be clear, practical, and beginner-friendly.

When documenting a feature, try to include:

What the feature does
Where the file should go
How to use it
A simple example
Any security considerations
Any common mistakes

NoClass™ documentation should avoid unnecessary jargon.

## 12. Commit Message Guidelines

Use clear commit messages.

Good examples:

Fix module model loading in Route.php
Add documentation for middleware folder structure
Improve route parameter handling
Update blog example controller

Less helpful examples:

fix stuff
changes
update
work

## 13. Third-Party Code

Do not copy third-party code into NoClass™ unless the licence allows it.

If you add third-party code, assets, or documentation, you must include the
correct licence and attribution information.

Third-party dependencies should be discussed before being added.

## 14. Community Behaviour

Be respectful and constructive.

NoClass™ is intended to be welcoming to:

Beginners
Procedural PHP developers
Developers who prefer simple architectures
Developers learning MVC
Developers building small, medium or large PHP applications
Developers who want a lightweight alternative to class-heavy frameworks

Disagreements are fine, but personal attacks, harassment, and abusive behaviour
are not acceptable.

## 15. Maintainer Decisions

The maintainers may decline contributions that do not fit the project direction.