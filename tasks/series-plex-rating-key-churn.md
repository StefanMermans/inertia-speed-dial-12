# Merging two Plex-created series discards a rating key and causes per-scrobble churn

**Status:** known, deferred
**Severity:** high (unbounded row growth and wasted TMDB quota; no data loss)
**Found:** code review of the Watch Progress feature, 2026-08-03
**Touches:** `app/Services/Watches/SeriesMerger.php`, `app/Listeners/SavePlexWatch.php`, `series` schema

---

## Summary

A `series` row can hold exactly one `plex_rating_key`. When two Plex-created series turn
out to be the same show and get merged, one of the two keys has to be thrown away. The
next scrobble that reports the discarded key finds no matching series, creates a fresh
one, syncs it, matches it to the same TMDB id, and merges it away again — on every
subsequent scrobble of that show, forever.

## Why it happens

`series.plex_rating_key` is a single, uniquely indexed column, so the relationship it
models is one-to-one. In reality a show can have several Plex rating keys, which makes it
one-to-many. The merge is where the schema and reality collide.

Two pieces of `SeriesMerger` combine into the bug:

- `salvageableAttributes()` copies a column from the loser only when the winner's is
  `null`. If the winner already has a `plex_rating_key`, the loser's is not rescued.
- `UNIQUE_COLUMNS` then nulls the loser's `plex_rating_key` before the row is retired.
  (This is necessary — a soft-deleted row keeps occupying the unique index, which would
  block the winner from ever claiming that key.)

Net effect: the key is gone from the database entirely.

`SavePlexWatch::updateOrCreateSeries()` looks a show up by exactly that key:

```php
Series::updateOrCreate(['plex_rating_key' => $metadata->grandparentRatingKey], [...])
```

so a key that no longer exists means "unknown show" and a new row is created.

## How a show ends up with two Plex rating keys

`grandparentRatingKey` is Plex's internal identifier, not a stable id for the show. It
differs when:

- a show is removed and re-added, or a library is rebuilt
- the same show appears in two libraries (e.g. a 4K library alongside a standard one)
- watches come from more than one Plex server

## The loop

1. Library A gives _Andor_ rating key `100` → series **P1**. Library B gives it `200` →
   series **P2**.
2. Both sync and resolve to TMDB `83867`. The unique index on `series.tmdb_id` forces a
   merge: P2 folds into P1. P1 keeps `100`; **`200` is discarded.**
3. The next scrobble from library B reports `grandparentRatingKey: 200`. Nothing matches,
   so a **new series P3 is created**.
4. P3 is new, so `needsFirstSync()` dispatches `SyncSeriesFromTmdb`. The matcher resolves
   `83867`, collides with P1, and merges P3 away — discarding `200` again.
5. Back to step 3, on every scrobble.

## Cost

Per scrobble of an affected show: one new `series` row, one TMDB sync (2–4 HTTP calls),
one merge.

The `series` table grows without bound. Since merges soft-delete, those churned rows are
never reclaimed.

**Watch data stays correct** — each watch is re-pointed onto the surviving series before
its row is retired, and `merged_into_id` covers anything that slips through. This is
churn and growth, not corruption.

## Why it is not currently biting

The common merge is a Plex-created row (has a key, no `tmdb_id`) folding into a manual
row created via _Add Watch_ (has a `tmdb_id`, no key). The winner's key is `null`, so the
loser's **is** salvaged and nothing is lost.

The loop needs **both** rows to carry a Plex key, i.e. two Plex-created series for one
show. Single-library setups that never re-add a show will not hit it.

## Reproducing

```php
$first = Series::factory()->create(['plex_rating_key' => '100', 'tmdb_id' => 83867]);
$second = Series::factory()->create(['plex_rating_key' => '200', 'tmdb_id' => null]);

app(SeriesMerger::class)->merge(into: $first, from: $second);

// '200' is now absent from the database
Series::withTrashed()->where('plex_rating_key', '200')->exists(); // false
```

Then replay a scrobble whose `grandparentRatingKey` is `200` and observe a new series row
appear rather than the existing one being reused.

## Possible fixes

### 1. `series_plex_keys` table — recommended

Model the relationship as it actually is.

```text
series_plex_keys
  id
  series_id        FK -> series, cascade on delete
  plex_rating_key  string, unique
```

- `SeriesMerger` re-points the loser's key rows at the winner instead of discarding them,
  so a series accumulates every key Plex has ever given it.
- `SavePlexWatch` resolves the show through the pivot, then falls back to creating a
  series plus its first key.
- Re-adding a show to Plex, or a second library, stops forking history — the new key just
  attaches to the existing series.

Cost: a migration with a backfill from the existing column, changes to the Plex write
path and the merger, and dropping `series.plex_rating_key` once nothing reads it. This is
the only option that actually fixes the modelling error.

### 2. Keep the column, only null the loser's key when it duplicates the winner's

Does **not** work. The key is still absent from the row Plex will ask for next time, so
step 3 of the loop is unchanged. Recorded here so it is not re-proposed.

### 3. JSON array column on `series`

Cheaper than option 1 — no join table — but gives up the unique index that currently
guarantees one series per key, and makes the lookup a JSON containment query that differs
between Postgres and SQLite (the test suite runs on SQLite). Not worth the saving.

### 4. Accept it, and reclaim the churn

Leave the modelling alone and add a scheduled prune of soft-deleted series with no
watches. Caps the table growth but leaves the TMDB calls and the merge on every scrobble.
A stopgap, not a fix.

## Test to write first

A regression test that fails against current code:

> a scrobble reporting a rating key that was discarded by an earlier merge reuses the
> surviving series instead of creating a new one

Build it with the existing Plex fixtures — `tests/Feature/Watches/SaveWatchTest.php` has
the `parseFixture()` / `dispatchScrobble()` helpers, and `grandparentRatingKey` can be
overridden through the metadata overrides argument.

---

## Related deferred items

From the same review, not yet addressed:

- **No HTTP timeout on the TMDB client.** Four calls at the 30s default can exceed the
  queue's `retry_after` of 90s, so a worker re-reserves the job and runs a second copy of
  the same merge concurrently. One-line fix: `->timeout(10)` on `TmdbApi::appClient()`.
- **`SyncSeriesFromTmdb`'s 404 branch operates on the merge winner.** `$series` is
  reassigned by `resolveMatch()`, so a 404 clears the match on a shared surviving row
  rather than the one that caused it. The stale-totals half of this is fixed; this half
  is not.
- **`WatchProgressQuery::moviesForUser()` has no test coverage.** The only movie test
  asserts the deferred prop is absent.
- **The N+1 guard in `WatchProgressControllerTest` is tautological.** It asserts
  `<= 5` queries against a 2-series fixture that issues 2; it would pass on the
  regression it exists to catch. Assert that 2 and 20 series produce an identical count.
