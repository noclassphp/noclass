# `SECURITY.md`


# Security Policy

Security is important to NoClass™.

NoClass™ is a procedural PHP MVC framework, and applications built with it may
handle user accounts, forms, sessions, database queries, admin panels, APIs,
uploads, and other sensitive functionality.

Please report security issues responsibly.

## Supported Versions

Security support is currently provided for the latest public release of NoClass™.

| Version | Supported |
| ------- | --------- |
| Latest release | Yes |
| Older releases | Best effort |
| Unreleased development branches | Best effort |

As the project matures, this policy may be updated to define long-term support
versions.

## Reporting a Vulnerability

If you discover a security vulnerability, please do not open a public GitHub
issue.

Instead, report it privately.

Use one of the following methods:

```text
Email: admin@noclass.org
GitHub Security Advisory: Use the private vulnerability reporting feature if enabled.
```

# What to Include

When reporting a vulnerability, please include as much detail as possible:

* A clear description of the issue
* Steps to reproduce the issue
* Affected files or functions
* Example request, route, payload, or configuration
* Expected behaviour
* Actual behaviour
* Possible impact
* Suggested fix, if known
* Your contact details, if you want follow-up communication

Please avoid including sensitive information from real users or production
systems.

# Examples of Security Issues

Security issues may include, but are not limited to:

*  SQL injection
* Coss-site scripting
* Cross-site request forgery
* Authentication bypass
* Authorisation bypass
* Session fixation
* Session hijacking risks
* Insecure file upload handling
* Path traversal
* Remote code execution
* Sensitive data exposure
* Insecure direct object references
* Unsafe routing behaviour
* Unsafe middleware behaviour
* Insecure error output
* Weak token generation
* Unsafe default configuration
* Public Disclosure

Please give the maintainers reasonable time to investigate and fix the
vulnerability before public disclosure.

Do not publish exploit code, proof-of-concept attacks, or detailed vulnerability
information publicly before a fix is available.

# Maintainer Response

After receiving a report, the maintainers will aim to:

* Confirm receipt of the report.
* Investigate the issue.
* Confirm whether it is a valid vulnerability.
* Prepare a fix where needed.
* Credit the reporter if they want credit.
* Publish a security release or advisory where appropriate.

Response times may vary depending on maintainer availability and issue
complexity.

# Security Fixes

Security fixes should be focused and minimal where possible.

A security fix should avoid unrelated refactoring unless required to solve the
issue.

# Safe Development Practices

Contributors are encouraged to follow secure PHP development practices:

* Validate input.
* Escape output.
* Use prepared statements or secure database helper functions.
* Avoid exposing raw errors to users.
* Protect admin routes.
* Use CSRF protection for state-changing forms.
* Use secure session configuration.
* Avoid storing secrets in the repository.
* Avoid trusting user-controlled paths.
* Restrict uploads by type, size, and storage location.
* Keep dependencies updated.
* NoClass™ Security Philosophy

NoClass™ aims to stay simple, readable, and predictable.

Security should be built into the framework without making the framework
unnecessarily complex.

Where possible, security-related functions should remain procedural, clear, and
easy to audit.