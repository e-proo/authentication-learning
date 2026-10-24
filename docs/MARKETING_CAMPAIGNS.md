# Marketing campaign workflow

All endpoints are scoped to an organization and require the relevant marketing permission.

## Create content

1. Create a campaign with a goal, budget, currency, optional store, schedule, and non-personal audience snapshot.
2. Add a channel. The `internal` channel uses `publish_mode=internal`. Platform channels such as Instagram, Facebook, Google Ads, TikTok, and Telegram must use `publish_mode=external`.
3. Add at least one creative linked to its target channel. Creative content can be text, image, video, or carousel; an asset reference is currently just a string, not an upload.

## Review and approval

1. Submit the draft campaign for review.
2. Submit each creative for review.
3. A user with `marketing.approve` approves reviewed creatives.
4. A user with `marketing.approve` approves the campaign after at least one creative is approved.
5. A user with `marketing.manage` can publish the internal channel after campaign approval.

The service rejects publishing when the channel is external or uses a platform adapter that is not implemented. It returns HTTP 501 instead of pretending the external ad was published.

## Metrics

Daily channel metrics can be recorded with impressions, clicks, conversions, spend, attributed revenue, date, and one of three source labels:
- `manual`: entered by an operator
- `imported`: imported from a report or integration outside this module
- `estimated`: modeled or estimated data

The API currently stores the source label but does not verify evidence supplied for imported metrics. These numbers should not be advertised as platform-verified or as accurate attribution without a provider integration, evidence IDs, and reconciliation.

## Main routes

- GET/POST `/api/v1/organizations/{organization}/campaigns`
- GET `/api/v1/organizations/{organization}/campaigns/{campaign}`
- POST `/campaigns/{campaign}/channels`
- POST `/campaigns/{campaign}/creatives`
- POST `/campaigns/{campaign}/submit`
- POST `/campaigns/{campaign}/creatives/{creative}/submit`
- POST `/campaigns/{campaign}/creatives/{creative}/approve`
- POST `/campaigns/{campaign}/approve`
- POST `/campaigns/{campaign}/channels/{channel}/publish`
- POST `/campaigns/{campaign}/channels/{channel}/metrics`
- GET `/campaigns/{campaign}/metrics`

The abbreviated route suffixes above are appended to the organization-scoped API prefix.

## Not implemented

External ad publishing and account authorization, creative media upload/processing, budget delivery/pacing, ad spend billing, click tracking pixels, conversion deduplication, attribution windows, refunds/returns attribution, and provider-specific report verification remain future work.
