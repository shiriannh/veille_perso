# AGENTS.md

## Project intent
This is a local-first personal cultural watch tool.
It is not a technical showcase.
The application must remain pragmatic, simple, and useful in daily use.

## Current state
V1.2 already supports:
- Source CRUD
- manual RSS import from a source screen
- automatic Entry creation from RSS items
- entity deletion buttons

## Current goal
Implement a post-RSS analysis pipeline to improve content relevance.

## Scope
- keyword-based relevance scoring
- heuristic clickbait detection
- optional AI-based classification for ambiguous cases
- visible decision and reasoning in the UI
- Symfony commands for analysis

## Non-goals
Do not add:
- scraping
- auth
- public API
- distributed workers
- vector database
- search engine
- destructive auto-deletion
- complex ML infrastructure

## Product rules
- RSS remains the only automatic input
- analysis happens after import
- entries are classified, not deleted
- decisions must remain explainable
- AI is optional and must not be required for the app to remain useful

## Expected decisions
Entry final decision should be one of:
- relevant
- maybe_relevant
- ignored
- clickbait

## Technical expectations
- Symfony conventions first
- Twig server-rendered UI
- Doctrine query builders for filtering
- Keep abstractions light
- No unnecessary service layers
- Favor readability over cleverness

## UX expectations
- sober UI
- useful filters
- clear flash messages
- explicit delete confirmations
- import history should be easy to read

## Delete rules
- a Source with existing Entry records must not be deletable
- an Entry linked to a Review must not be deletable
- no silent destructive cascade behavior

## Deduplication expectations
Document and implement this priority:
1. externalId / guid
2. canonicalUrl
3. stable business hash

## Done means
A change is done when:
- code is coherent
- pages are wired in navigation when relevant
- database migrations are correct
- flash messages are present
- README is updated if behavior changed
