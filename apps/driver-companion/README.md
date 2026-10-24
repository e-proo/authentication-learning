# Wasl Driver Companion

A dedicated mobile app workspace for courier and logistics drivers. It is intentionally separate from platform administration, branch offices, WMS, dispatch, fleet management and finance.

## Current status

This is an early interactive client shell with local demonstration data. It is **not connected to the Laravel API** and does not implement real GPS tracking, camera/barcode scanning, push notifications, production offline storage, proof-media upload, cash posting, or authenticated server sync. Buttons that simulate a workflow are local/demo behavior. Do not use demo records for real operations.

The product requirements and acceptance gates are in ../../docs/DRIVER_COMPANION_SPEC.md. Cross-application boundaries are in ../../docs/APPLICATIONS_AND_WORKSPACES.md.

## Requirements

- Node.js compatible with the current Expo SDK; use Node 22.13+.
- npm.
- Android device/emulator or iOS simulator. For browser preview, install web dependencies and use the web script.

## Run

From this directory:

```powershell
npm install
npx expo install --fix
npm run start
```

Then open with Expo Go / a development build on a device. For browser testing:

```powershell
npx expo install react-dom react-native-web @expo/metro-runtime
npm run web
```

The exact native capabilities (camera, location, background execution, notifications and durable encrypted offline storage) must use Expo-supported native modules and be tested on target devices before being enabled.

## Planned modules

- Shift and eligibility
- Route and stop execution
- Pickup, delivery and proof of delivery
- Branch/hub transfers and manifest scanning
- Returns and failed-delivery recovery
- COD collection, cash handover and settlement status
- Vehicle inspection and incident reporting
- Dispatcher communication and notifications
- Offline outbox, idempotent sync, media retry and conflict resolution
- Privacy-aware shift-scoped location

## Architecture rules

- The mobile client calls authorized application APIs; it never writes directly to database tables.
- A driver can access only assigned jobs and permitted fields.
- Local completion is not server-confirmed completion until acknowledged by the server.
- Cash collection is separate from handover and reconciliation.
- Route/task state transitions and proof requirements are validated server-side.
