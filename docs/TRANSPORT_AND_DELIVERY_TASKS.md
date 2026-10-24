# Fleet, trips, and delivery tasks

All endpoints are under `/api/v1/organizations/{organization}` and require a Sanctum bearer token plus the organization's `transport.manage` or `delivery_tasks.manage` permission as appropriate.

## Fleet

Create a driver:

`POST /drivers`

    {
      "driver_code": "DRV-001",
      "display_name": "Example Driver",
      "phone": "+967700000000",
      "driver_type": "employee"
    }

Create a vehicle:

`POST /vehicles`

    {
      "vehicle_code": "VAN-001",
      "registration_plate": "ABC-123",
      "vehicle_type": "van",
      "capacity_kg": 900
    }

Driver types are employee, contractor, or partner. Vehicle types include motorcycle, car, van, truck, heavy_truck, and other. Inactive/suspended drivers and vehicles cannot be assigned.

## Trips

Create a trip with origin/destination snapshots:

`POST /transport-trips`

    {
      "trip_type": "last_mile",
      "origin": {
        "name": "Main Warehouse",
        "city": "Sanaa",
        "country_code": "YE",
        "address_line1": "Warehouse street"
      },
      "destination": {
        "name": "Delivery Area",
        "city": "Sanaa",
        "country_code": "YE",
        "address_line1": "Market street"
      }
    }

Assign driver and optional vehicle:

`POST /transport-trips/{trip}/assign`

    {
      "driver_id": 1,
      "vehicle_id": 1
    }

A driver or vehicle cannot be assigned to two active trips. A trip can depart only after assignment; it can be completed only after departure and after linked delivery tasks have reached a terminal status. Active tasks prevent trip cancellation or reassignment.

## Delivery tasks

Create a last-mile task for a shipment:

`POST /delivery-tasks`

    {
      "task_type": "last_mile",
      "shipment_id": 1,
      "location": {
        "name": "Recipient",
        "city": "Sanaa",
        "country_code": "YE",
        "address_line1": "Recipient street"
      },
      "recipient_name": "Recipient Name",
      "recipient_phone": "+967700000000"
    }

Assign it to the trip's driver:

`POST /delivery-tasks/{task}/assign`

    {
      "driver_id": 1,
      "transport_trip_id": 1,
      "sequence_number": 1
    }

Depart the trip, then update the task through:

`POST /delivery-tasks/{task}/events`

Task state flow: queued → assigned → en_route → arrived → completed. A failed task can be reassigned, incrementing its attempt number. Cancellation is available before the task starts. Invalid state transitions return a conflict response.

When a last-mile task moves to en_route, a shipment that has not yet been picked up is picked up through the shipment service; stock is reconciled in that transaction and the shipment progresses to out_for_delivery. A task cannot be marked completed until arrival is recorded and a proof reference and recipient name are supplied. Completion then marks the shipment delivered using its normal state-transition service.

Task types also include collection, hub_delivery, and return_pickup. A return-pickup task must refer to an approved return case. This association records the logistics task but does not itself receive the returned goods; warehouse receipt is still recorded through the returns receipt endpoint.

## Evidence and limitations

- `proof_reference` is currently a reference string, not an uploaded or verified file. Do not treat arbitrary strings as legally verified proof of delivery.
- GPS tracking, route optimization, mobile push notifications, driver offline synchronization, geo-fence checks, external carrier APIs, and signature/photo evidence storage are not implemented.
- Resource assignment is enforced within the organization; production use still needs branch-scoped policies and more concurrency/integration testing.
