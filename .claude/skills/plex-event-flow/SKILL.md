---
name: plex-event-flow
description: This explains the full flow from plex event (tv show or movie) and how they're handled
---

# Plex Event Flow

## Overview

Plex is a tv show and movie streaming service sending webhook events to this application

## Flow

```
Plex Server (webhook)
    │
    ▼
POST /api/plex-event
    │
    ▼
PlexEventController
    │
    ├─ Parse request into PlexEventRequestData → PlexEventData
    │   └─ On validation failure: report InvalidPlexEventException, return 204
    │
    ├─ Check: is it a scrobble? (event === 'media.scrobble')
    │   ├─ Yes → dispatch PlexScrobbleEvent
    │   └─ No  → do nothing
    │
    └─ Return 204 No Content (always)
```

## Key Components
