# Driver Companion API

The Driver Companion is a dedicated operational surface for authenticated drivers. It is not a fleet-admin screen and must not expose tasks belonging to another driver profile.

Base path: `/api/v1/driver`

All endpoints require a valid Sanctum-authenticated user. Requests are additionally rate-limited by the authenticated API middleware.

## Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/me` | Return the authenticated user's linked driver profiles and active shifts |
| GET | `/vehicles?driver_profile_id={id}` | List eligible vehicles attached to the selected driver's active trips |
| GET | `/tasks` | List tasks assigned to the user's active driver profiles; supports `driver_profile_id`, `status`, and pagination |
| GET | `/tasks/{task}` | Read one task only when assigned to a linked active profile |
| POST | `/tasks/{task}/events` | Submit a supported task status transition |
| GET | `/shifts/current` | Read active shifts for linked active driver profiles |
| POST | `/shifts/start` | Start a shift for a linked active driver profile |
| POST | `/shifts/{shift}/end` | End the authenticated user's active shift |

## Task transition rules

The driver endpoint accepts only `en_route`, `arrived`, `completed`, or `failed`. The domain service remains responsible for validating whether the current task state can transition to the requested state.

- The task is scoped to a linked, active driver profile before submitted details are validated.
- Assignment is checked again inside a transaction with a row lock before applying the transition.
- Completing a task requires a non-empty proof reference.
- Completing a `last_mile` task also requires the recipient name.
- A failed task requires a failure reason.
- The task transition and its event/audit records are handled by the logistics domain service.

## Vehicle and shift safety

- A driver cannot start a second active shift while one is already active.
- A vehicle must be active, assigned to the driver's active trip, and not already in use by another active shift.
- Vehicle shifts require all pre-trip checklist items to be recorded as passed and a starting odometer reading.
- A shift cannot end while active delivery tasks remain unresolved.
- Vehicle shifts require an ending odometer value that is not lower than the starting value and a complete post-trip checklist.
- A reported critical defect sends the vehicle to maintenance and marks the shift for review.

## Important limitations

A `proof_reference` is a reference supplied to the API; it is **not** proof that a photo or signature was uploaded, verified, or retained. Before production, connect this field to a controlled evidence-upload service with integrity checks, access controls, and retention policy.

Driver-facing clients should use these endpoints as a separate application surface. Dispatching, fleet management, warehouse operations, and office workflows belong to their own role-specific interfaces.
