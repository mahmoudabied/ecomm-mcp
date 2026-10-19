# Changelog

## 1.0.0 — 2026-10-19

First stable release.

- Klaviyo routing per channel and flow template, with native fallback when no active connection exists.
- Historical sync of customers, orders and catalog through the outbox, scoped to the connection channel.
- Consent mirroring: store consent changes become Klaviyo subscribe/unsubscribe events; Klaviyo-sourced changes are not echoed back.
- Unsubscribe pull (`klaviyo:pull-consent`) requesting `include=profile,metric` and revoking matching store consents per channel with a per-connection cursor.
