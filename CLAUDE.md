# Working on this repository

Laravel exam management system: exam programs, room allocation, and the
distribution of faculty members to exam rooms (see `docs/distribution.md`).

## Always document the work

Every change is documented as part of the change itself, not afterwards:

- Add an entry to `docs/maintenance-log.md` for each change: what changed,
  why, its commit, and anything users or operators will notice or must do.
- When behaviour or structure changes, update the doc that describes it
  (`docs/distribution.md`, `README.md`, or a new page under `docs/`).
- Commit messages explain the reason for the change, not only what changed.

## Checks before committing

- `vendor/bin/phpunit` must pass (in-memory SQLite, no setup needed).
- `DistributionSnapshotTest` locks the distribution results. Re-record with
  `UPDATE_SNAPSHOTS=1` only for an intended behaviour change, review the
  fixture diff, and describe the change in the maintenance log.

## Conventions

- Distribution code lives in `app/Services/Distribution`; controllers stay thin.
- Role names in `course_room_rotation_user.roleIn` are stored data
  (`RoomHead`, `Secertary`, `Observer`); don't rename them without a migration.
- Never commit database dumps or other personal data.
