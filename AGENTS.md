# AGENTS.md

## Project intent
This project is a personal local-first cultural watch tool.
It is meant to be useful in daily life, not a technical showcase.

## Product philosophy
- local first
- monolith first
- simple architecture
- pragmatic implementation
- no microservices
- no SPA frontend for V1
- no overengineering
- code must remain easy to read and maintain

## Technical stack
- Symfony 7
- PHP 8.4
- PostgreSQL 16
- Apache 2.4
- Adminer
- Docker Compose

## Coding expectations
- Prefer Symfony conventions over custom abstractions
- Prefer simple CRUD controllers and Twig templates for V1
- Use Doctrine cleanly, with explicit relationships
- Avoid unnecessary service classes unless they add clear value
- Avoid premature optimization
- Keep configuration explicit and easy to understand
- Favor readability over cleverness

## V1 scope
Implement:
- Source management
- Entry management
- Review management
- Home dashboard
- Database migrations
- Local Docker environment
- README with exact setup steps

Do not implement in V1:
- authentication
- public API
- scraping engine
- AI enrichment
- background workers
- message bus
- search engine
- event sourcing
- microservices

## Domain guidance
Entities expected:
- Source
- Entry
- Review

Entry stores the spotted raw item.
Review stores the enriched personal judgment.
A Review may not exist for every Entry.

Use timestamps consistently:
- createdAt
- updatedAt

## UI guidance
- Server-rendered HTML only
- Twig templates
- Keep the UI sober and functional
- Minimal CSS
- Bootstrap allowed only if it speeds up delivery cleanly

## Delivery guidance
Work in small coherent steps.
After each major step, ensure the project is still runnable.
Prefer atomic commits if the environment supports commits.
Document important design choices briefly in README.
