# Maintenance log: security, correctness and performance pass

**Period:** September–October 2026
**Branch:** `claude/blissful-keller-aipojo`, based on `new` at `478eb2a`
**Imported from:** `aliabodaraa/Exam_Managment_` (history kept; original authors unchanged)

This log records what was changed, why, and what it means for the people
running and using the system. Each item lists its commit. For how the
distribution works now, see [distribution.md](distribution.md). Known
limitations kept on purpose are in [known-issues.md](known-issues.md).

Sections 1–7 cover the first pass. Later work is added as dated entries
under [Later entries](#later-entries).

## Summary

- Closed privilege escalation and account takeover holes in user management.
- Fixed seven correctness bugs, four of them in the distribution
  (doctors observing their own course, objections leaking through shared
  time slots, missed overlaps, unstable sorting).
- Rewrote the distribution engine and the save step. On a real rotation they
  produce **identical results** with **18 queries instead of ~26,850**
  (about 4.2 s → 0.2 s on SQLite, more on MySQL over a network).
- Added a test suite where there was none: 267 tests covering access rules,
  distribution rules, invariants, the endpoint, and 226 recorded scenarios.
- Removed personal data from the repository's current files.

## 1. Security

| Commit | Change | Why |
|---|---|---|
| `4c47e17` | Removed `data/*.sql` and ignored `data/*.sql`. | The dumps held real staff names, emails and password hashes, publicly. |
| `55061ef` | `AdminAccess` reads the route user safely; admin check moved to `User::isAdmin()` (`User::ADMIN_TEMPORARY_ROLES`). Unauthorized requests get 403 instead of 404. | Non-admins opening courses, rooms or rotations pages hit an "undefined index" error. |
| `c8c4c54` | New `adminOnly` middleware on create/delete/activate user, teaching courses and bulk observation reset. `adminAccess` (own account or admin) on profile edit/update and on rotation objections. | Any logged-in user could create, delete or deactivate accounts, change teaching assignments, reset everyone's quota, and edit other users' profiles and objections. |
| `b2fe73b` | `UsersController::store/update` save only whitelisted fields. Non-admins can change only their email and username. Passwords change only through old/new/confirm on your own account, checked with `Hash::check`. | `temporary_role` and `password` were mass-assignable from the request, so any user could make themselves admin or overwrite another user's password. |
| `6e5d2a2` | `UserAuthorizationTest` (and a working `UserFactory`, SQLite test config). | Locks in the rules above. |

## 2. Correctness

| Commit | Change | Why |
|---|---|---|
| `27daba6` | `.env.example` has an empty `APP_KEY`. | `APP_KEY=cp .env.example .env` stopped the app from booting. |
| `e824882` | Restored the Spatie permission tables migration under its original name. | It was never committed; deleting a user failed on a fresh database. Existing databases skip it. |
| `67ec2ec` | Objection buttons on the exam program page pass the user. | Rendering the page threw a missing-parameter error. |
| `1c426ca` | Saving a distribution runs in one transaction. | A failure part-way left a half-saved distribution. |
| `06cb910` | Sort comparators return `<=>`. | Boolean comparators are deprecated and unreliable; results unchanged on PHP 8. |
| `7f1dbb4` | Doctors are excluded from the courses they teach. | The helper returned a nested array, so the exclusion never matched. |
| `698c2b0` | A member who objects to (or teaches) one course of a same-time group is kept out of the whole group. | The flow could route them into that course through another course in the group; this happened in 13 of 25 random test scenarios. |
| `05cb026` | Overlapping exams are grouped by tracking the group's latest end time. | Only neighbouring exams were compared, so an exam overlapping a non-adjacent one could double-book a member. |
| `28c713d` | Distributing a rotation that already has assignments is refused with a warning. | It failed with a 500 on duplicate rows. |
| `7224f24` | `composer.json` requires PHP `^8.1`. | The code needs 8.1 (enums, readonly properties); the old constraint allowed installing on PHP 7.3/8.0. Lock file: same package versions. |

## 3. Distribution engine and performance

The work kept the original behaviour exactly, apart from the fixes above,
and proved it at every step.

| Commit | Step |
|---|---|
| `a8708ae` | Extracted the three role passes from the controller into a `MembersDistributor` service. Added `MaxFlow::resetState()` because the algorithm kept state in static properties. |
| `6c6355d` | Renamed the original engine to `LegacyMaxFlowMembersDistributor`; added realistic (`large`) and contention-heavy (`tight`) test scenarios. |
| `c44df7a` | Added the optimized engine (`RotationData`, `RoleNetworkBuilder`, `FlowNetwork`, `AugmentingPathSolver`, `SelectionState`). A parity test required identical output to the original on 228 scenarios, including ones exercising rerouting and the first-room rule. |
| `bc1ade0` | Switched the app to the optimized engine. |
| `f59e313` | Moved the original save code into `LegacyDistributionWriter`; added endpoint tests. |
| `1f30eee` | Added the bulk `DistributionWriter`; a parity test required identical saved rows on 53 scenarios, most of which copy members into shared rooms. |
| `1a2c77d` | Switched to the bulk writer; dropped the 6-minute `max_execution_time` override. |
| `40cbebf` | Recorded the original implementation's results for 226 scenarios as fixtures (`DistributionSnapshotTest`). The original and the optimized code both match them. |
| `07f3bde` | Removed the original implementation (`app/Http/Controllers/MaxFlow`, the legacy classes and parity tests); the role enum is now `App\Services\Distribution\MemberRole`. |

### Measurements

Real rotation 30 (67 courses, 662 course rooms, 28 rooms), in-memory SQLite.
The real data was used only locally for this measurement; it was not committed.

| | Original | Now | Output |
|---|---|---|---|
| Distribute | 3.10 s, 15,121 queries | 0.19 s, 12 queries | identical |
| Save | 1.14 s, 11,732 queries | 0.02 s, 6 queries | identical rows |

Pure SQL time was a few percent of the original total; the rest was
Eloquent overhead per query and matrix scans. On MySQL every one of the
~26,850 queries also paid a network round trip.

### Why not port to another language

Discussed during the work: rewriting in NestJS, Spring or C++ would not have
fixed any of the problems above, which were missing authorization, logic
bugs, and queries inside loops. The problem is small (a few hundred nodes);
PHP solves it in milliseconds once the data is loaded up front.

## 4. What users will notice

- Distribution finishes in about a second instead of minutes.
- Non-admins get **403** on admin pages and actions (previously 404, or a
  crash, or the action succeeded).
- Non-admins can no longer change their own role, quota, faculty or
  property through the profile form.
- Doctors are no longer assigned to observe exams of courses they teach.
- Members are no longer placed in a course they objected to, nor in two
  overlapping exams.
- Running the distribution twice shows a warning instead of an error page;
  reset the observations first.

The last three points can change who is assigned compared with earlier
runs; those earlier assignments broke the rules.

## 5. Actions needed outside the code

1. **Change the exposed passwords.** The SQL dumps with password hashes are
   still in the git history of both this repository and
   `aliabodaraa/Exam_Managment_`. Everyone in them should change their password.
2. **Purge the dumps from history** (optional but recommended), keeping all
   commit authors:
   ```
   git filter-repo --path data --invert-paths
   git push --force origin new
   ```
   Everyone with a clone must re-clone afterwards.
3. **Deploy on PHP 8.1 or newer.**
4. **Run `php artisan migrate`.** Existing databases already have the
   permission tables, so the restored migration is skipped.
5. **Check one real distribution in production** (or a copy) for timing and results.

## 6. Follow-ups

- **Upgrade Laravel.** Version 8 is out of support, and `composer audit`
  reports advisories against the locked packages.
- **Rooms with several observers.** The model gives exactly one person per
  role per room.
- **Distribution behaviour** (rooms with several observers, explicit
  fairness, one definition of "same time"): kept as is for now by decision
  of 2026-10-04 and documented in [known-issues.md](known-issues.md).
- **Explicit fairness and priorities.** A single network with role phases,
  or min-cost flow, could replace the heuristics with a clear objective.
  That is a deliberate behaviour change, done by re-recording the snapshots
  (see distribution.md).
- **Unify the two "same time" definitions** used by the network and by the
  shared-room step.
- **Clean up remaining controller code** (`Stock`, views with inline queries).

## 7. Running the tests

```
composer install
cp .env.example .env && php artisan key:generate
vendor/bin/phpunit
```

Tests use in-memory SQLite and need no database server. 267 tests pass.
On PHP 8.4, Laravel 8 prints deprecation notices; they don't affect the results.

## Later entries

### 2026-10-04: documentation rule and known issues

- Added `CLAUDE.md` with the working rules for this repository. Every change
  is documented as part of the change: a maintenance-log entry, updates to
  the docs it affects, and a commit message that explains why.
- Added [known-issues.md](known-issues.md). By decision, the distribution
  behaviour stays as it is for now: one person per role per room, heuristic
  fairness, two definitions of "same time", group-wide exclusions, and
  "first match wins" when copying staff into shared rooms. Each item lists
  a possible fix.
- Decided: upgrade to Laravel 12 (PHP 8.2+). The SQL dumps stay in the git
  history for now; the owner will purge them (steps in section 5).

### 2026-10-04: pages that crashed on Linux; page smoke tests

- `RotationsController` loaded `rotations.index`, `rotations.create` and
  `rotations.edit`, but the folder is `resources/views/Rotations`. That works
  on case-insensitive filesystems (Windows, macOS) and crashes on Linux
  servers. The view names now match the folder.
- Removed the `rooms.show` route and `RoomsController::show()`: the view never
  existed and nothing linked to it, so the route could only return an error.
- Added `PageSmokeTest`: every page renders for an admin on a rotation with a
  saved distribution, the observations export downloads, and login works
  with username or email (and rejects a wrong password). It is the baseline
  for the Laravel upgrade.
- Found while listing routes: three "reset" actions delete data on a GET
  request. Added to [known-issues.md](known-issues.md).


### 2026-10-04: removed laravelcollective/html

- The package is abandoned and has no release for current Laravel. It was used
  only for four delete forms (`layouts/partials/popUpDelete`,
  `users/edit_user_courses`, `courses/add_user_courses`). They are now plain
  Blade forms with `@csrf` and `@method('DELETE')`, posting to the same routes.
- The teaching-course delete form passed its route parameters nested one level
  too deep. The plain form passes `['user' => …, 'course' => …]` directly.
- Covered by a new smoke test asserting the delete forms point at the delete
  routes. No visible change for users.
