# Feature Specification: Marketing Automation (Email, SMS & WhatsApp Flows + Klaviyo Connector)

**Feature Branch**: `002-marketing-automation`
**Created**: 2026-10-08
**Status**: Draft
**Input**: User description: "Marketing automation: email/SMS/WhatsApp flows plus a Klaviyo connector. MVP: flow engine with email, WhatsApp and SMS channels; providers WhatsApp Cloud API, Twilio, Vonage/MessageBird, local MENA SMS gateways."

## Context

Bagisto stores today can send one-off newsletter campaigns and a single fixed abandoned-cart reminder (via a paid add-on). Merchants cannot build automated, multi-step customer journeys (welcome series, post-purchase, win-back) or reach customers on WhatsApp and SMS — the dominant channels in MENA markets. They also cannot sync their store data to Klaviyo, the most widely used e-commerce marketing platform. This feature closes that gap.

## Clarifications

### Session 2026-10-08

- Q: How is consent initialized for existing customers/subscribers at install? → A: Existing newsletter subscribers get email marketing consent (source "migrated – newsletter"); no one gets SMS/WhatsApp consent until they opt in.
- Q: How does WhatsApp → SMS fallback work? → A: Built-in per-step fallback channel, used when the primary channel lacks consent, valid contact, or connected provider, or the send fails permanently.
- Q: Klaviyo routing options per flow type? → A: Native, Klaviyo, or Disabled — mutually exclusive; no "both".
- Q: Do quiet hours and frequency caps apply to transactional messages? → A: No — transactional messages bypass both and do not count toward caps.
- Q: Retention for message/journey logs? → A: 12 months default (configurable), then purged; aggregate metrics and attribution kept indefinitely.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Recover abandoned carts automatically across channels (Priority: P1)

A store admin turns on a prebuilt "Abandoned Cart" flow. When a shopper (logged in, or a guest who entered an email or phone at checkout) leaves items in their cart without ordering, the store waits a configurable time, then sends a reminder by email; if the shopper still hasn't ordered, it follows up on WhatsApp (or SMS if WhatsApp isn't available/consented). The reminder links straight back to a restored cart. The moment the shopper places an order, any remaining steps are cancelled.

**Why this priority**: Abandoned cart recovery is the single highest-revenue automation in e-commerce and the most requested. It alone justifies the product and exercises the whole engine (trigger → delay → condition → multi-channel send → exit on goal).

**Independent Test**: Enable the prebuilt flow with short delays, abandon a cart as a test customer, verify the email arrives with a working restore-cart link, verify the WhatsApp/SMS follow-up arrives, then repeat and place an order before the second step and verify no further messages are sent.

**Acceptance Scenarios**:

1. **Given** the Abandoned Cart flow is active with a 1-hour first delay, **When** a customer with items in their cart is inactive for 1 hour without ordering, **Then** they receive the email step containing the cart items, totals, and a link that restores the cart.
2. **Given** a customer received step 1, **When** they place an order before step 2 is due, **Then** step 2 is never sent and the customer exits the flow as "converted".
3. **Given** a guest entered only a phone number at checkout and consented to WhatsApp, **When** the flow runs, **Then** email steps are skipped and the WhatsApp step is sent.
4. **Given** a customer already received an abandoned-cart flow within the configured re-entry window (default 7 days), **When** they abandon another cart, **Then** they are not re-enrolled.
5. **Given** a recovered order, **When** the admin opens the flow report, **Then** the order and its revenue are attributed to the flow and the step that was last clicked/sent.

---

### User Story 2 - Build and manage custom flows (Priority: P1)

A store admin creates a flow from scratch or from a template (Welcome Series, Post-Purchase / Review Request, Win-Back, Order Status Updates, Back-in-Stock). They choose a trigger event, then add steps in sequence: wait (fixed duration or until a time of day), condition/branch (e.g., "has placed an order", "order total > X", "customer group is Y", "has WhatsApp consent"), send message (email, SMS, WhatsApp), and exit. They can activate, pause, and duplicate flows, and send a test of any message step to themselves.

**Why this priority**: The engine must be general so merchants can model their own journeys; prebuilt templates make it usable on day one.

**Independent Test**: Create a "Welcome" flow triggered by customer registration with email → wait 2 days → condition "has not ordered" → WhatsApp with a coupon. Register a test customer and observe each step execute in order.

**Acceptance Scenarios**:

1. **Given** an admin with marketing permission, **When** they create a flow with a trigger and at least one send step and activate it, **Then** new matching trigger events enrol customers from that moment onward (no retroactive enrolment unless explicitly chosen).
2. **Given** an active flow with enrolled customers, **When** the admin pauses it, **Then** no further steps are sent; **When** they resume it, **Then** pending steps continue from where they stopped (overdue steps older than 24 h are skipped rather than sent late).
3. **Given** a condition step, **When** a customer reaches it, **Then** they follow the "yes" or "no" branch according to their current data at that moment.
4. **Given** an admin editing a message step, **When** they click "Send test", **Then** the message is delivered to the address/number they specify using sample or a chosen customer's data.
5. **Given** a flow with invalid configuration (e.g., WhatsApp step with no approved template), **When** the admin tries to activate it, **Then** activation is blocked with a clear explanation.

---

### User Story 3 - Configure messaging channels and providers (Priority: P1)

A store admin connects their sending providers: email uses the store's existing mail settings; WhatsApp uses the Meta WhatsApp Cloud API or Twilio; SMS uses Twilio, Vonage, MessageBird, or a supported regional gateway (e.g., Unifonic, Msegat, Taqnyat, Cequens, Victory Link). They enter credentials, set a sender ID/number, send a test message, and see whether the connection works. They can set a default provider per channel and per sales channel (storefront).

**Why this priority**: No SMS/WhatsApp step can run without a configured provider; regional gateways are the reason MENA merchants will choose this over competitors.

**Independent Test**: Enter credentials for one SMS and one WhatsApp provider, send test messages, confirm receipt; enter invalid credentials and confirm a clear error.

**Acceptance Scenarios**:

1. **Given** valid provider credentials, **When** the admin clicks "Test connection", **Then** a test message is delivered and the provider is marked "connected".
2. **Given** invalid credentials, **When** testing, **Then** the admin sees the provider's error message in plain language and the provider is not marked connected.
3. **Given** a WhatsApp provider is connected, **When** the admin syncs templates, **Then** the store lists the provider's approved message templates (with language and variable placeholders) for use in flow steps.
4. **Given** stored credentials, **When** any admin views the settings page, **Then** secrets are masked and never displayed in full.

---

### User Story 4 - Respect customer consent and preferences (Priority: P1)

Customers explicitly opt in per channel (email marketing, SMS, WhatsApp) at registration, checkout, and in their account. Every marketing email has a one-click unsubscribe; SMS honours STOP replies where the provider supports it; WhatsApp honours opt-out. Transactional flows (e.g., order status updates) are distinguished from marketing flows. Quiet hours and per-customer frequency caps prevent over-messaging.

**Why this priority**: Sending without consent is illegal in many markets (GDPR, anti-spam laws) and gets WhatsApp numbers banned. This must ship with the first release.

**Independent Test**: Register a customer who opts into email only; trigger a flow with email + WhatsApp steps and verify only email is sent; click unsubscribe and verify no further marketing emails are sent.

**Acceptance Scenarios**:

1. **Given** a customer without WhatsApp consent, **When** a flow reaches a marketing WhatsApp step, **Then** the step is skipped (logged as "skipped – no consent") and the flow continues.
2. **Given** a customer clicks the unsubscribe link, **When** they confirm, **Then** their email marketing consent is revoked immediately and recorded with timestamp and source.
3. **Given** quiet hours of 22:00–09:00 in the store's timezone, **When** an SMS/WhatsApp step becomes due at 23:00, **Then** it is held until 09:00.
4. **Given** a frequency cap of 2 marketing messages per channel per 24 hours, **When** a third would be sent, **Then** it is deferred until the cap allows, or skipped if the deferral exceeds the step's validity.
5. **Given** a transactional flow (order shipped notice), **When** it sends, **Then** it does not require marketing consent but still requires a valid contact for that channel and never contains promotional content blocks, and it is sent immediately even during quiet hours without counting toward the frequency cap.

---

### User Story 5 - Write personalized, multilingual messages (Priority: P2)

An admin composes message content using a visual email editor with reusable blocks (header, text, button, product grid, cart items, coupon, footer) and inserts personalization variables (first name, cart items, order number, recommended products, a unique auto-generated coupon). Each message can have a version per store locale (e.g., Arabic and English, with right-to-left layout for Arabic); the customer receives the version matching their locale.

**Why this priority**: Personalization and native-language messages drive conversion; but basic plain-content messages are enough to deliver P1 value.

**Independent Test**: Create an email with name, cart-items block, and a unique coupon in Arabic and English; trigger for an Arabic-locale customer and an English-locale customer and confirm each receives the correct language with correct data and a distinct working coupon.

**Acceptance Scenarios**:

1. **Given** a message with a unique coupon block tied to an existing cart rule, **When** sent to two customers, **Then** each receives a different single-use code that is valid at checkout.
2. **Given** a customer whose locale is Arabic and the message has an Arabic version, **When** sent, **Then** the Arabic version is delivered with right-to-left layout; if no Arabic version exists, the default locale version is sent.
3. **Given** a variable has no value for a customer (e.g., missing first name), **When** rendering, **Then** the configured fallback text is used rather than an empty or broken placeholder.

---

### User Story 6 - Measure flow performance (Priority: P2)

An admin opens a flow and sees per-flow and per-step metrics: enrolled, sent, delivered, opened (email), clicked, failed, skipped, unsubscribed, converted orders, and attributed revenue over a selected date range. They can drill into a customer's journey log to see exactly which steps ran and why any were skipped.

**Why this priority**: Merchants need proof of ROI to keep paying; but flows deliver value before reports exist.

**Independent Test**: Run the abandoned-cart flow for several test customers with mixed outcomes and verify the report counts and attributed revenue match the underlying activity.

**Acceptance Scenarios**:

1. **Given** a customer clicks a flow message and orders within the attribution window (default 5 days for clicks), **When** viewing the report, **Then** the order revenue is attributed to that flow and step once (no double counting across flows; last click wins).
2. **Given** a provider reports a message as failed, **When** viewing the step metrics, **Then** it counts as failed with the provider's reason visible in the journey log.

---

### User Story 7 - Sync store data to Klaviyo (Priority: P3)

A merchant who already uses Klaviyo connects their Klaviyo account with an API key. The store then sends customer profiles (with consent status), key events (Viewed Product, Added to Cart, Started Checkout, Placed Order, Ordered Product, Fulfilled Order, Cancelled Order, Refunded Order), and the product catalog to Klaviyo, and pulls back unsubscribes so consent stays consistent. The merchant chooses, per flow type, whether it runs natively, in Klaviyo, or is disabled (mutually exclusive), so customers aren't messaged twice.

**Why this priority**: Captures merchants already invested in Klaviyo and is a competitive differentiator, but it is independent of the native engine and can ship after it.

**Independent Test**: Connect a Klaviyo test account, place a test order, and verify the profile, "Placed Order" event, and product appear in Klaviyo within minutes; unsubscribe the profile in Klaviyo and verify consent is revoked in the store.

**Acceptance Scenarios**:

1. **Given** a valid Klaviyo key, **When** the admin connects and runs initial sync, **Then** existing customers (with consent status), the last 12 months of orders, and the active catalog are sent, with progress visible.
2. **Given** an active connection, **When** a customer places an order, **Then** "Placed Order" and one "Ordered Product" per line item arrive in Klaviyo within 5 minutes.
3. **Given** Klaviyo is temporarily unavailable, **When** events occur, **Then** they are queued and delivered once it recovers, with no duplicates.
4. **Given** the admin sets "Abandoned cart handled by Klaviyo", **When** a cart is abandoned, **Then** the native abandoned-cart flow does not send.

---

### Edge Cases

- Customer merges guest cart into account after login → only one enrolment, keyed to the customer.
- Product in an abandoned cart goes out of stock or is disabled → message renders without it (or the step is skipped if the cart becomes empty).
- Cart contents change after enrolment → messages render the current cart at send time.
- Customer deleted or GDPR erasure requested → pending enrolments are cancelled and personal data removed from logs; anonymized aggregate metrics remain.
- Phone numbers stored in local formats → normalized to international format using the customer's/store's country; invalid numbers cause a "skipped – invalid contact" log entry.
- Provider rate limits or outages → sends are retried with back-off for up to 24 hours, then marked failed.
- WhatsApp 24-hour session rules → marketing/utility messages outside a session always use an approved template.
- A flow is edited while customers are mid-journey → in-progress customers continue on the version they enrolled in; new enrolments use the new version.
- Multiple sales channels (storefronts) → flows, providers, and sender identity can be scoped per channel.
- Duplicate trigger events (e.g., same order event fired twice) → enrolment is idempotent.
- Clock/timezone: delays and quiet hours use the store channel's timezone.

## Requirements *(mandatory)*

### Functional Requirements

**Flow engine**

- **FR-001**: System MUST allow admins to create, edit, duplicate, activate, pause, and delete flows, each scoped to one or more sales channels.
- **FR-002**: System MUST support these triggers: cart abandoned, customer registered, newsletter subscribed, order placed, order status changed (to any status), order shipped, order delivered/completed, product back in stock (for subscribed customers), customer inactive for N days since last order, and customer birthday.
- **FR-003**: System MUST support step types: wait (duration or until time of day / weekday), condition (yes/no branch), send email, send SMS, send WhatsApp, and exit.
- **FR-003a**: Each SMS/WhatsApp send step MUST allow an optional fallback channel (SMS, WhatsApp, or email) that is used instead when the primary channel cannot be used for the recipient (no consent, no valid contact, no connected provider) or the send fails permanently (non-retryable error or retries exhausted); the log MUST record that the fallback was used and why.
- **FR-004**: Conditions MUST support at least: has placed order since enrolment, order count, lifetime spend, last order total, customer group, locale, channel, has consent for a channel, cart value, and product/category in cart or order.
- **FR-005**: Each flow MUST have configurable exit goals (e.g., "placed an order") that immediately end enrolment when met.
- **FR-006**: System MUST prevent duplicate enrolment for the same customer and trigger event, and MUST support a re-entry window per flow.
- **FR-007**: Enrolled customers MUST continue on the flow version they entered; edits apply to new enrolments.
- **FR-008**: System MUST ship prebuilt flow templates: Abandoned Cart (3 steps), Welcome Series, Post-Purchase Review Request, Win-Back, Order Status Updates (transactional), Back-in-Stock.
- **FR-009**: Admins MUST be able to send a test of any message step to a specified email/phone.
- **FR-010**: Flows MUST be classified as marketing or transactional; transactional flows cannot include promotional blocks (coupons, product recommendations).
- **FR-011**: Abandoned cart MUST be detected for logged-in customers and for guests who provided an email or phone during checkout; the abandonment delay MUST be configurable (default 1 hour).
- **FR-012**: Messages MUST include a secure link that restores the abandoned cart on any device without requiring login, and expires after a configurable period (default 7 days).

**Channels & providers**

- **FR-013**: Email MUST use the store's existing outgoing mail configuration, with configurable sender name/address per channel.
- **FR-014**: WhatsApp MUST support Meta WhatsApp Cloud API and Twilio, including syncing approved templates and mapping variables.
- **FR-015**: SMS MUST support Twilio, Vonage, MessageBird, and at least three regional MENA gateways at launch (Unifonic, Msegat, Taqnyat), with a provider abstraction allowing more to be added without changing flows.
- **FR-016**: Admins MUST be able to set a default provider per channel per sales channel, test the connection, and see connection status.
- **FR-017**: Provider credentials MUST be stored encrypted and displayed masked.
- **FR-018**: System MUST record delivery status updates from providers where available (sent, delivered, read, failed) and email opens/clicks.
- **FR-019**: Failed sends due to transient errors MUST be retried with back-off for up to 24 hours.
- **FR-020**: Phone numbers MUST be normalized to international format before sending; invalid numbers MUST be skipped and logged.

**Consent & compliance**

- **FR-021**: System MUST store per-customer, per-channel consent (email marketing, SMS marketing, WhatsApp marketing) with timestamp, source, and channel, and expose opt-in controls at registration, checkout, and in the customer account. On installation, existing newsletter subscribers MUST be granted email marketing consent with source "migrated – newsletter"; no SMS or WhatsApp consent is inferred for existing contacts.
- **FR-022**: Marketing steps MUST be skipped for customers without consent on that channel; transactional steps MUST NOT require marketing consent.
- **FR-023**: Every marketing email MUST include a one-click unsubscribe link and list-unsubscribe header; SMS MUST honour STOP/opt-out where the provider supports inbound messages.
- **FR-024**: Admins MUST be able to configure quiet hours (SMS/WhatsApp) and a per-customer frequency cap per channel. Quiet hours and frequency caps apply only to marketing messages; transactional messages are sent immediately regardless of quiet hours and do not count toward frequency caps.
- **FR-025**: GDPR data export and erasure requests MUST include and remove marketing automation data (consent records, journey logs, message logs).

**Content & personalization**

- **FR-026**: Admins MUST be able to compose emails with a block-based visual editor (header, text, image, button, product grid, cart items, order summary, coupon, footer) and SMS/WhatsApp bodies with variables.
- **FR-027**: Messages MUST support variables with fallbacks: customer first/last name, store name, cart items, cart total, order number, order items, order total, tracking link, review link, restore-cart link, recommended products, and unique coupon.
- **FR-028**: The unique coupon block MUST generate a single-use code per recipient from an existing cart rule chosen by the admin.
- **FR-029**: Each message MUST support one version per store locale; recipients receive their locale's version or the default; right-to-left locales MUST render correctly.

**Reporting**

- **FR-030**: System MUST report per flow and per step: enrolled, sent, delivered, opened, clicked, skipped (with reason), failed, unsubscribed, conversions, and attributed revenue, filterable by date range and sales channel.
- **FR-031**: Revenue attribution MUST use last-click within a configurable window (default 5 days click, 1 day open/delivered for SMS/WhatsApp without click tracking), counting each order once.
- **FR-032**: Admins MUST be able to view a per-customer journey log for any flow.
- **FR-032a**: Detailed message logs and journey logs MUST be retained for a configurable period (default 12 months) and then purged automatically; aggregate per-flow/per-step metrics and attribution records MUST be retained so reports for past date ranges remain accurate after purge.

**Klaviyo connector**

- **FR-033**: Admins MUST be able to connect a Klaviyo account by private API key and choose which sales channels sync.
- **FR-034**: System MUST sync customer profiles with consent status, the product catalog, and events: Viewed Product, Added to Cart, Started Checkout, Placed Order, Ordered Product, Fulfilled Order, Cancelled Order, Refunded Order.
- **FR-035**: System MUST run a one-time historical sync (customers, last 12 months of orders, active catalog) with visible progress, then sync incrementally.
- **FR-036**: System MUST pull unsubscribes/consent changes from Klaviyo back into the store.
- **FR-037**: Event delivery to Klaviyo MUST be queued, retried, and idempotent (no duplicates).
- **FR-038**: Admins MUST be able to designate, per flow type, whether it runs natively, in Klaviyo, or is disabled (exactly one; a flow type cannot run in both), to prevent double-messaging.

**Access control**

- **FR-039**: All features MUST be governed by admin role permissions (view flows, manage flows, manage providers/settings, view reports, manage Klaviyo).

### Key Entities

- **Flow**: A named automation with trigger, type (marketing/transactional), sales channels, re-entry window, exit goals, status (draft/active/paused), and versions.
- **Flow Version**: An immutable snapshot of the flow's step graph that enrolments are bound to.
- **Flow Step**: A node in the flow (wait, condition, send, exit) with its configuration and branches.
- **Message Template**: Content for a send step per channel and locale, including variables and blocks. It's stored inside the flow version's step definition, not as a separate record. For WhatsApp it references a provider-approved template, which is synced as its own record (WhatsApp Template).
- **Enrolment**: A customer's (or guest contact's) journey through one flow version: trigger reference, current step, next run time, status (active, completed, converted, exited, cancelled).
- **Message Log**: One send attempt: enrolment, step, channel, provider, recipient, status history, provider message ID, error, opens/clicks.
- **Contact Consent**: Per-customer/guest, per-channel consent state with timestamp, source, and history.
- **Provider Connection**: Channel, provider type, encrypted credentials, sender identity, sales channel scope, status.
- **Abandoned Cart Record**: Cart reference, contact, detected time, restore token, recovery status, recovered order.
- **Attribution**: Link between an order and the flow/step/message credited for it.
- **Klaviyo Connection & Sync Job**: Account connection, sync settings, historical sync progress, event queue status.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A merchant can activate the prebuilt Abandoned Cart flow with email + WhatsApp in under 10 minutes from installing the extension (with provider credentials in hand).
- **SC-002**: A merchant can build a custom 4-step flow (trigger, send, wait, condition, send) in under 15 minutes without documentation.
- **SC-003**: 99% of due messages are dispatched within 5 minutes of their scheduled time (outside quiet hours and frequency caps).
- **SC-004**: Zero marketing messages are sent to a customer without consent for that channel (verified by audit of message logs vs consent records).
- **SC-005**: Zero further abandoned-cart messages are sent after a customer places an order (beyond messages already in flight at the moment of ordering).
- **SC-006**: The store sustains 10,000 enrolled customers per day across flows with no measurable slowdown of storefront page loads or checkout.
- **SC-007**: Reported attributed revenue matches the sum of the underlying attributed orders exactly for any date range.
- **SC-008**: Klaviyo events for orders appear in Klaviyo within 5 minutes in 99% of cases, with zero duplicates over a 30-day test.
- **SC-009**: Pilot merchants recover at least 5% of abandoned carts through the flow within their first 30 days.

## Assumptions

- The store has a working queue worker and scheduler running; without them, flows cannot execute on time (setup check will warn).
- Email sending reuses Bagisto's existing mail configuration; no separate email service provider integration in v1.
- Merchants obtain their own provider accounts, sender IDs, and WhatsApp template approvals; the extension does not provide messaging credit.
- WhatsApp messages initiated by the store always use approved templates; two-way conversational WhatsApp (chat inbox) is out of scope.
- Regional SMS gateways beyond Unifonic, Msegat, and Taqnyat (e.g., Cequens, Victory Link) are added post-launch through the same provider abstraction.
- Product recommendations in messages use simple rules (related/up-sell products, bestsellers in the same category); AI recommendations are out of scope.
- A/B testing of message content, push notifications, and one-off broadcast campaigns to segments are out of scope for v1 (existing newsletter campaigns remain).
- Guest abandoned-cart capture relies on the email/phone field at checkout being filled; no exit-intent popups in v1.
- The Klaviyo connector is one-way for store data (store → Klaviyo) except consent/unsubscribes (Klaviyo → store); Klaviyo-sent messages are not reported in native flow reports.
- The extension is delivered as an installable package within this Bagisto codebase and must work with the Amacommerce theme and the default Shop theme.
