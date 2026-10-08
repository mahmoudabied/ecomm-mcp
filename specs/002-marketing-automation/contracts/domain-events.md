# Contract: Domain Events (extension points)

Dispatched via `Event::dispatch('<name>', $payload)` following Bagisto naming, so other packages (incl. `Webkul\Klaviyo`) can listen.

| Event | Payload | When |
|---|---|---|
| `marketing_automation.consent.changed` | `Consent $consent, ConsentEvent $event` | any consent grant/revoke (Klaviyo listens to push subscription state) |
| `marketing_automation.flow.activate.after` | `Flow $flow, FlowVersion $version` | |
| `marketing_automation.flow.pause.after` / `.resume.after` / `.delete.before` | `Flow` | |
| `marketing_automation.enrolment.create.after` | `Enrolment` | |
| `marketing_automation.enrolment.finish.after` | `Enrolment` (status completed/converted/exited/cancelled) | |
| `marketing_automation.message.send.before` | `MessageLog`, `OutboundMessage\|Mailable` | listeners may veto by returning `false` |
| `marketing_automation.message.send.after` | `MessageLog` | |
| `marketing_automation.message.status.after` | `MessageLog`, `string $status` | webhook / tracking update |
| `marketing_automation.cart.abandoned` | `AbandonedCart` | detection |
| `marketing_automation.attribution.create.after` | `Attribution` | |

Consumed core events: see research.md R4/R5/R12/R16/R17.
