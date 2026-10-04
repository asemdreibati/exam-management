# Known issues

Behaviour that is known to be limited or questionable but is **kept as is
on purpose** (decision of 2026-10-04). Changing any distribution item
below changes who gets assigned, so it needs a decision, a rule test and
re-recorded snapshots (see "Changing the behaviour" in
[distribution.md](distribution.md)).

## Distribution

### 1. Exactly one person per role per room

Each room gets one room head, one secretary and one observer. The course →
room edge in the network has capacity 1 for every role. Large rooms that
need two or more observers cannot be expressed. (The README used to say
"2 observers per room"; that was never what the code did.)

*Possible fix:* give rooms an "observers needed" count (for example from
capacity or student numbers) and use it as the course → room capacity for
the observer role.

### 2. Fairness relies on heuristics

Max-flow fills as many seats as possible but has no notion of a balanced
load. Spreading work comes from three selection heuristics inherited from
the original code (postponing members already taken, the "first room"
rule, rerouting), described in [distribution.md](distribution.md#the-solver).
They are hard to reason about, and their outcome depends on node order:
members with the most objections first, courses with the most objections
first.

*Possible fix:* min-cost flow with increasing costs per extra assignment
for the same member, or one network with role phases. Either gives an
explicit, testable objective.

### 3. Two definitions of "same time"

- The **network** groups courses on the same date whose exams **overlap**,
  using each exam's own duration, including chains of overlaps. Nobody is
  assigned twice within a group.
- **Saving** copies staff between courses that share a room when the other
  course **starts** within *this* course's duration before or after its
  start (from `Stock::getDisabledAndJoiningRoomsAndCommonCoursesWithTime`).
  This window ignores the other course's duration and is not symmetric.

So two courses can be "at the same time" for one step and not for the
other. In practice this only matters for rooms shared by courses with
different start times.

*Possible fix:* use the overlap groups for both steps.

### 4. One exclusion blocks a whole same-time group

A member who objects to (or, as a doctor, teaches) one course of a
same-time group is kept out of every course in that group. That is what
stops the flow from routing them into the excluded course. The objection
form already records objections for every course at the same date and time,
so this mostly matches what members ask for. But with overlapping exams at
different times it excludes more than they asked.

*Possible fix:* per-member course edges instead of shared group nodes, as
in the single-network design.

### 5. Copying staff into shared rooms: first match wins

When courses around the same time share a room, the members already placed
there for one course are copied to the others with nobody there. Once a
course with members is found, every later course sharing the room is
treated as staffed, even if it is not. The result depends on the program's
database order.

### 6. A role with no assignable member stops the whole distribution

If no room head (or secretary, or observer) can be assigned at all, nothing
is saved and the admin gets a warning. If only *some* seats cannot be
filled, the distribution is saved with those seats empty, and there is no
report of which seats are missing.

*Possible fix:* report unfilled seats after each distribution.

## Elsewhere in the application

- **Role names are hard-coded Arabic strings** (`دكتور`, `رئيس شعبة الامتحانات`,
  `عميد`, …) compared in controllers, middleware and views. The admin check
  is centralised in `User::isAdmin()`; the rest is not.
- **`Secertary` is misspelled in stored data** (`course_room_rotation_user.roleIn`).
  Renaming it needs a data migration.
- **Views run queries inline** (for example `Rotations/ExamProgram/show.blade.php`
  and the `Stock` helpers it calls), so some pages issue many queries.
- **`Stock` time arithmetic** adds two `strtotime()` timestamps. That only
  works while `config/app.php` uses the `UTC` timezone.
- **Destructive actions are GET links.** `rotations.initExamProgram`,
  `initRoomsInAllCourses` and `initUsersObservationsInAllCourses` delete a
  rotation's program, rooms or observations on a plain GET request, so they
  have no CSRF protection and a crafted link opened by an admin would wipe
  data. They should become POST/DELETE forms.
- **Student distribution** (`RotationsController::distributeStudents`) has not
  been reviewed or tested yet.
