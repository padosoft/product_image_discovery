---
name: product-image-discovery
description: Use when working on the padosoft/product-image-discovery Laravel package, including product image discovery, matching, scoring, queue jobs, API endpoints, search providers, trusted source logic, anti-false-positive checks, image quality rules, optional sidecar behavior, tests, docs, installation instructions, and release readiness.
---

# Product Image Discovery Package Skill

Use this skill when working on the `padosoft/product-image-discovery` Laravel package.

## When To Use

- Implementing or reviewing product image discovery, matching, scoring, queue jobs, API endpoints or sidecar behavior.
- Adding search providers, trusted source logic, anti-false-positive checks or image quality rules.
- Updating tests, docs, package installation instructions or release readiness.

## First Reads

Read these files before editing:

- `docs/LESSON.md`
- `docs/PROGRESS.md`
- `docs/RULES.md`
- `AGENTS.md`
- `config/product-image-discovery.php`
- `src/ProductImageDiscoveryServiceProvider.php`

## Core Principles

- Optimize for precision, not maximum recall.
- Keep AI, browser rendering and live search providers optional.
- Use deterministic, explainable matching first: source, text, structured data, image quality and stored scoring.
- Preserve idempotency across all jobs.
- Keep package code app-agnostic and configurable.

## Commands

Install dependencies:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar' install
```

Validate Composer:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\composer.phar' validate --strict
```

Run PHP tests:

```powershell
& 'C:\Users\lopad\.config\herd\bin\php84\php.exe' vendor\bin\phpunit --testsuite Unit,Feature,E2E
```

Run sidecar tests:

```powershell
npm test
```

## Completion Checklist

- Unit, Feature and E2E PHPUnit suites pass.
- Sidecar Node tests pass if sidecar code changed.
- Live external tests are skipped unless explicitly configured.
- `docs/PROGRESS.md` and `docs/LESSON.md` reflect new discoveries.
- README remains accurate about what is implemented vs optional/roadmap.
