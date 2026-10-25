# Driver Companion API

The Driver Companion is a dedicated operational surface for authenticated drivers. It is not a fleet-admin screen and must not expose tasks belonging to another driver profile.

Base path: `/api/v1/driver`

All endpoints require a valid Sanctum-authenticated user. Requests are additionally rate-limited by the authenticated API middleware.

## Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/me` | Return the authenticated user's linked driver profiles and active shifts |
| GET | `/vehicles?driver_profile_id={id}` | List eligible vehicles attached to the selected driver's active trips |
| GET | `/tasks/summary` | Return task counts by status and type for the linked active driver profile(s) |
| GET | `/tasks` | List tasks assigned to the user's active driver profiles; supports `driver_profile_id`, `status`, `task_type`, and pagination |
| GET | `/tasks/{task}` | Read one task only when assigned to a linked active profile |
| GET | `/tasks/{task}/events` | Read paginated status and activity history for a task assigned to the linked active profile(s) |
| POST | `/tasks/{task}/evidence` | Upload private proof-of-delivery evidence for an active task |
| POST | `/tasks/{task}/events` | Submit a supported task status transition |
| GET | `/shifts/current` | Read active shifts for linked active driver profiles |
| POST | `/shifts/start` | Start a shift for a linked active driver profile |
| POST | `/shifts/{shift}/end` | End the authenticated user's active shift |

## Standalone driver interface

The independent Arabic RTL web client lives in `apps/driver-app`. See `apps/driver-app/README.md` for Node.js requirements and local run commands. It consumes only the `/api/v1/driver` API surface for driver operations; office, warehouse, and fleet-admin interfaces remain separate.

## Driver dashboard summary

Call `GET /api/v1/driver/tasks/summary` to build the driver's home-screen counters without downloading the full task list. Optionally pass `driver_profile_id` to scope the summary to one linked active profile.

The response includes:

- `total`: total tasks across the selected active profile(s)
- `active`: tasks currently `assigned`, `en_route`, or `arrived`
- `by_status`: counts grouped by each status currently present in the data
- `by_type`: counts grouped by task type currently present in the data

## Driver task list filters

The task list accepts these optional query parameters:

| Parameter | Allowed values | Default |
| --- | --- | --- |
| `driver_profile_id` | Integer ID of a driver profile linked to the authenticated user | All linked active profiles |
| `status` | `queued`, `assigned`, `en_route`, `arrived`, `completed`, `failed`, `cancelled` | All statuses |
| `task_type` | `collection`, `hub_delivery`, `last_mile`, `return_pickup` | All task types |
| `per_page` | Integer from 1 to 100 | 50 |

Example: `GET /api/v1/driver/tasks?status=assigned&task_type=last_mile&per_page=20`

The response uses Laravel pagination metadata, so clients can request the next page without downloading every task at once. The service applies the driver-profile scope before filters, preventing these filters from widening access to another driver's tasks.

## Task event history

Call `GET /api/v1/driver/tasks/{task}/events` to retrieve the task's recorded event history. The endpoint verifies task assignment to a linked active driver profile before returning events and supports `driver_profile_id` and `per_page` (1–100, default 30). Events are returned newest first with pagination metadata.

## Task transition rules

The driver endpoint accepts only `en_route`, `arrived`, `completed`, or `failed`. The domain service remains responsible for validating whether the current task state can transition to the requested state.

- The task is scoped to a linked, active driver profile before submitted details are validated.
- Assignment is checked again inside a transaction with a row lock before applying the transition.
- Upload evidence through `POST /tasks/{task}/evidence` using multipart form data (`file`, `evidence_type`, and optional `driver_profile_id`). Accepted file types are JPG, PNG, WEBP, and PDF, up to 10 MB.
- Evidence is stored on the private local filesystem disk under a task- and organization-scoped directory; the API returns a `proof_reference`, not a public URL.
- A driver cannot complete a task with an arbitrary text reference: the reference must point to an existing stored file in that exact task's evidence directory.
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

The evidence endpoint verifies file type and size and stores evidence privately. Production deployment still needs an explicit retention/deletion policy, malware scanning, and an authorized evidence-view/download endpoint for dispatch or claims staff. The `evidence_type` label is user-declared metadata and does not cryptographically prove that a file is a signature or photograph.

Driver-facing clients should use these endpoints as a separate application surface. Dispatching, fleet management, warehouse operations, and office workflows belong to their own role-specific interfaces.
