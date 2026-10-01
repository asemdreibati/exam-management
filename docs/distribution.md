# Member distribution

How the system assigns faculty members to exam rooms as **room heads**,
**secretaries** and **observers** for a rotation (دورة امتحانية).

## Where the code lives

| File | Responsibility |
|---|---|
| `app/Http/Controllers/RotationsController.php` → `distributeMembersOfFaculty` | Endpoint behind "Distribute Members on Rooms". Refuses if the rotation already has assignments, runs the distributor, saves in one transaction. |
| `app/Services/Distribution/MembersDistributor.php` | Interface: `distribute(Rotation): DistributionResult`. Bound in `AppServiceProvider`. |
| `app/Services/Distribution/MaxFlowMembersDistributor.php` | The engine: one max-flow run per role, in order. |
| `app/Services/Distribution/MaxFlow/RotationData.php` | Loads everything the engine needs in ~12 queries. |
| `app/Services/Distribution/MaxFlow/RoleNetworkBuilder.php` | Builds one role's flow network. |
| `app/Services/Distribution/MaxFlow/FlowNetwork.php` | Sparse directed graph with residual capacities. |
| `app/Services/Distribution/MaxFlow/RoleNetwork.php` | A network plus the meaning of its nodes (which member/course/room a node is). |
| `app/Services/Distribution/MaxFlow/AugmentingPathSolver.php` | Ford–Fulkerson with BFS and the selection heuristics. |
| `app/Services/Distribution/MaxFlow/SelectionState.php` | Heuristic state carried from one role's run to the next. |
| `app/Services/Distribution/DistributionResult.php` | Per-role assignments, or the role that could not be filled. |
| `app/Services/Distribution/DistributionWriter.php` | Saves the result, including members of rooms shared by same-time courses. |
| `app/Services/Distribution/MemberRole.php` | `RoomHead`, `Secertary`, `Observer` (names are stored in the database). |

## Request flow

1. An admin posts to `rotations.distributeMembersOfFaculty` (`adminAccess` middleware).
2. If `course_room_rotation_user` already has rows for the rotation, the
   request is refused with a warning asking to reset the observations
   ("تهيئة المراقبات", `rotations.initUsersObservationsInAllCourses`) first.
3. `MembersDistributor::distribute()` computes the assignments. Nothing is written.
4. If a role could not be filled at all, the admin gets the matching warning
   and nothing is saved.
5. Otherwise `DistributionWriter::save()` runs inside `DB::transaction`, so
   either the whole result is saved or none of it.

## Input data

| Table | Used for |
|---|---|
| `course_rotation` | The exam program: course, `date`, `time`, `duration` (`"H:MM"`). |
| `course_room_rotation` | The rooms each course's students sit in. |
| `initial_members_for_each_rotation` | Who may be a room head (`options = {"1":"on"}`), a secretary (`{"2":"on"}`) or both (`{"1":"on","2":"on"}`). |
| `users` | `number_of_observation` (quota), `role` (`دكتور` = doctor), `temporary_role`, `is_active`. |
| `course_rotation_user` | Objections: courses a member cannot observe. The objection form records every course held at the same date and time. |
| `course_teacher` | Courses a doctor teaches; a doctor never observes them. |

### Member pools

- **Room heads**: initial members with option 1. Doctors among them come first.
- **Secretaries**: initial members with option 2.
- **Observers**: active users without a temporary role, plus secretaries,
  minus room heads.

A member can be in several pools (a secretary is also an observer). Their
quota is shared across roles.

## The network for one role

Roles are distributed in order: room heads, then secretaries, then
observers. Each role gets its own network:

```
source ─quota─▶ member ─1─▶ same-time group ─#rooms─▶ course ─1─▶ room ─∞─▶ sink
```

- **Member nodes**: members with the most exclusions (objections plus
  taught courses) come first; ties keep pool order. The edge from the source
  carries the member's quota minus what earlier roles already gave them.
- **Same-time groups**: courses on the same date whose exams overlap,
  directly or through a chain of overlapping exams (A 09:00–12:00 and
  C 10:00 are grouped even if a short exam B sits between them). A member
  is linked to a group with capacity 1, so nobody serves twice at the same
  time. A member is **not** linked to a group if they object to, or teach,
  any course in it, or already serve in it in an earlier role.
- **Course nodes**: the most-objected courses come first; ties keep date
  and time order. Group → course capacity is the number of rooms the course
  uses.
- **Course → room**: capacity 1, meaning **one person per role per room**.
  If two courses of the same group share a room, only the first course
  (in node order) keeps the edge, so a shared room is staffed once per role.
- **Room → sink**: unlimited.

Every unit of flow from source to sink is one assignment:
*member X serves as role R in room Z for course Y*.

## The solver

`AugmentingPathSolver` repeatedly finds a shortest augmenting path with
breadth-first search, scanning neighbours in ascending node order, and
pushes one unit along it. Three heuristics shape which paths are found.
They come from the original implementation and are kept as they were:

1. **Spreading work.** A member who already received a path is postponed
   once in the BFS queue when reached from the source, so other members get
   a turn. The memory of who was taken resets once as many paths were
   counted as there are members.
2. **First-room rule.** After several consecutive paths for the same member,
   the first room node may be closed to the next search when the next course
   in that member's group still has more than one open room including it.
3. **Rerouting.** When the best path reroutes flow through other members, it
   is split into the direct paths it implies: the last five nodes become a
   new assignment, and each earlier member/group pair moves an existing
   assignment from one member to the next.

The state behind rules 1–2 (`SelectionState`) carries over from one role's
run to the next within one distribution, as it did in the original code.
A distribution is deterministic: the same data always gives the same result.

## Result shape

`DistributionResult` holds, per role:

```php
['users_observations' => [
    $userId => [
        ['course' => 12, 'room' => 4, 'date' => '2024-01-10', 'time' => '09:00:00', 'duration' => '1:30', 'roleIn' => 'RoomHead'],
        ...
    ],
]]
```

Members appear in member-node order and each member's assignments in the
order their paths were found.

## Saving

`DistributionWriter` inserts one `course_room_rotation_user` row per
assignment. It then handles rooms shared by courses held around the same
time:

- Two courses count as "around the same time" when, on the same date, the
  other course **starts** within this course's duration before or after this
  course's start. This window comes from the original
  `Stock::getDisabledAndJoiningRoomsAndCommonCoursesWithTime` and is
  narrower than the overlap groups above.
- Courses are visited in the program's database order. For each room a
  course shares with such courses, the members already placed there for one
  of them are copied to the courses that have nobody in that room yet. Once
  a course with members is found, later ones sharing the room count as
  staffed too.

All rows are inserted in chunks of 500 at the end.

## Guarantees

Checked on every result by `DistributionInvariantsTest`:

- members only fill roles they are eligible for;
- nobody observes a course they objected to, or (doctors) teach;
- nobody exceeds their quota;
- each (course, room, role) seat has at most one person;
- nobody is assigned to two overlapping exams.

## Known limitations

- **One person per role per room.** Rooms needing more than one observer are
  not modelled.
- **Fairness is heuristic.** Max-flow fills as many seats as possible;
  spreading work relies on the postponement rule, not on an explicit
  balancing objective.
- **Exclusions block a whole same-time group.** Objecting to one course of a
  group keeps the member out of the group. This is what prevents the flow
  from routing them into that course.
- **Two definitions of "same time".** The network groups overlapping exams;
  the shared-room step uses the start-within-duration window described
  above.
- **Role names are stored.** `roleIn` holds `RoomHead`, `Secertary` (sic)
  and `Observer`; renaming them needs a data migration.

## Tests

Run all tests with `vendor/bin/phpunit` (in-memory SQLite, no setup needed).

| Test | What it covers |
|---|---|
| `tests/Support/DistributionScenario.php` | Builds a rotation in the database from short names, or a seeded random one (`small`, `tight` = few members and small quotas, `large` = real-rotation size). |
| `DistributionSnapshotTest` | 226 random scenarios compared with results recorded from the original implementation: assignments per role in order, and the saved rows. |
| `DistributionInvariantsTest` | The guarantees above on 25 random scenarios. |
| `DistributionRulesTest` | Hand-built cases: teaching doctors, objections inside an overlap group, overlap through a non-adjacent exam. |
| `DistributeMembersEndpointTest` | The endpoint: an admin's distribution is saved, a repeated distribution is refused, a non-admin gets 403. |

## Changing the behaviour

`DistributionSnapshotTest` fails on **any** change in who is assigned where.
That is intentional. To change the behaviour on purpose:

1. Make the change and add or adjust a rule test that describes it.
2. Re-record the snapshots: `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --filter DistributionSnapshotTest`.
3. Review the diff of `tests/fixtures/distribution/*.json` and confirm every
   difference is expected.
4. Make sure `DistributionInvariantsTest` still passes.

The fixtures contain only generated names (`H1`, `C3`, `R2`, …), no real data.

## Performance

Measured on a real rotation (67 courses, 662 course rooms, 28 rooms, 100+
role members) on in-memory SQLite:

| Step | Original | Now |
|---|---|---|
| Computing the distribution | 3.1 s, 15,121 queries | 0.19 s, 12 queries |
| Saving it | 1.1 s, 11,732 queries | 0.02 s, 6 queries |

The original cost was mostly queries issued inside loops (per member, per
course, per path) plus a V×V matrix scan for every augmenting path. Against
MySQL each query also pays a network round trip, which is why the endpoint
needed a 6-minute time limit before.
