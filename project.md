 Package Design
Core Concept
The package owns two things exclusively: serialisation (converting whatever the host app gives it into a valid spec-compliant payload) and transport (sending and receiving that payload to/from Core). Everything else — models, database schema, retry storage, queue workers — belongs to the host application.
The integration contract between the package and the host app is a set of interfaces. The host app implements those interfaces against its own models; the package never touches Eloquent, Doctrine, or any ORM directly.

The Interface Contract
The package defines a set of PHP interfaces that the host app must implement. The package only ever works with these, never with concrete models.
OrderBridgeOrderInterface
OrderBridgeLineItemInterface
OrderBridgePaymentInterface
OrderBridgeAncillaryInterface
OrderBridgeCustomerInterface
OrderBridgeFulfilmentInterface
Each interface exposes exactly the fields the spec requires for that object. The host app writes an adapter — a thin class that wraps its own model and implements the interface. The package never knows what's underneath.

Package Components
1. Serialiser
Takes a collection of objects implementing OrderBridgeOrderInterface and a trigger value, and produces a fully validated, spec-compliant payload array ready for JSON encoding. Handles all the arithmetic — line_ex_vat, line_vat, total_ex_vat, items_net, grand_total — using bcmath internally so the host app never needs to compute these. The host app provides raw values (qty, unit_price, discount, vat_rate); the package derives all computed fields.
2. Validator
Implements all rules from §5 of the spec. Can be called independently of the transport layer — useful for host apps that want to validate a payload before queuing it, or to dry-run a batch. Returns a structured result object with any violations listed per order and per line, rather than throwing on the first error.
3. Transport
Handles the HTTP conversation with Core. Sends the payload, interprets the response, and returns a typed result. Knows nothing about queues — that is the host app's concern. The transport layer is deliberately thin: build the payload, POST it, return success or a structured failure. Retry logic, backoff, and offline queuing all live in the host app.
4. Inbound Parser
The reverse direction. Takes a raw JSON payload received from Core (an export), validates it against the spec, and returns a collection of typed DTO objects. The host app then maps those DTOs into its own persistence layer. The package never writes to any database.
5. transfer_id Manager
A small utility the host app can use to generate and reuse transfer_id values correctly. Generates UUID v4, and provides a helper to determine whether a payload is a genuine new transfer or an identical retry — so the host app can decide whether to reuse or regenerate the ID without having to understand the idempotency rules itself.

What the Package Deliberately Does Not Include

No Eloquent models or migrations
No queue jobs
No retry logic or failed transfer storage
No HTTP client configuration beyond what the host provides
No Laravel service provider that assumes a specific framework (the package should be framework-agnostic, with an optional Laravel bridge as a separate package — atlasflow/order-bridge-laravel)


Typical Integration Flow
Host app order completes
       ↓
Host app wraps its order model in an adapter implementing OrderBridgeOrderInterface
       ↓
Passes adapter(s) to Serialiser → gets back a validated payload array
       ↓
Host app queues the payload however it likes
       ↓
Queue worker calls Transport with the payload
       ↓
Transport POSTs to Core, returns result
       ↓
Host app marks orders as synced in its own way
The package is involved only in the two middle steps. Everything before and after is the host application's responsibility.

Optional Laravel Bridge (atlasflow/order-bridge-laravel)
A separate thin package that provides the Laravel-specific wiring: a service provider, a config file (order-bridge.php), a facade, and a pre-built base adapter class that Atlas Cassa and other Laravel apps can extend rather than implementing the interfaces from scratch. This keeps the core package clean and usable by non-Laravel applications.

This design means a WooCommerce plugin, a Laravel ePOS, and a custom PHP marketplace can all use the same atlasflow/order-bridge package against the same spec, each providing their own adapter implementations without any of them being aware of each other's persistence layer.