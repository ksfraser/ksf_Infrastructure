# Event-Driven Architecture for KSF FrontAccounting Modules

## Overview

Every CRUD action that affects cross-module state MUST emit an event via FA's hook system. This ensures modules remain decoupled - no direct dependencies between modules. A module emits an event without knowing who listens; a module listens without knowing who emitted.

**Core Principle:** Modules communicate through events, not direct calls.

---

## 1. Event Naming Convention

Events use `snake_case` with the pattern `{object}_{action}`:

| Event | Meaning |
|-------|---------|
| `stock_reserved` | Stock was reserved for an SO |
| `stock_released` | Stock reservation was released (fulfilled, cancelled, voided) |
| `stock_insufficient` | Stock level below threshold during SO creation |
| `suggested_po_created` | A suggested PO was auto-generated |
| `suggested_po_approved` | A suggested PO was approved |
| `po_created` | A PO was created (manual or auto) |
| `so_created` | A sales order was created |
| `so_modified` | A sales order was modified |
| `so_delivered` | A sales order was delivered |
| `so_invoiced` | A sales order was invoiced |
| `grn_received` | Goods received against a PO |
| `task_created` | A task was created in Teams |
| `task_completed` | A task was marked complete |
| `quality_issue_created` | A quality issue/8D was logged |

---

## 2. Standard Event Payload

All events pass `$data` by reference with these standard fields:

```php
$data = [
    'module'        => 'ksf_FA_StockReservations',  // Emitting module
    'event'        => 'stock_reserved',              // Event name
    'timestamp'    => '2024-01-15 14:30:00',       // ISO 8601

    // Event-specific fields below
    'stock_id'     => 'SKU-001',
    'quantity'     => 10,
    // ...
];
```

---

## 3. Event Map — Modules, Emissions, and Listeners

### 3.1 Stock Reservations (`ksf_FA_StockReservations`)

| Event Emitted | Trigger | Listeners |
|--------------|---------|-----------|
| `stock_reserved` | SO created with reservable stock | SuggestedPO, Teams |
| `stock_released` | SO voided, delivery complete, or cancelled | SuggestedPO, Teams |
| `stock_insufficient` | SO creation fails stock check | SuggestedPO, Teams |

**Emitted Methods:**
- `db_postwrite($cart, ST_SALESORDER)` → emit `stock_reserved`
- `db_prevoid(ST_SALESORDER, $trans_no)` → emit `stock_released`

### 3.2 Suggested Purchase Orders (`ksf_FA_SuggestedPO`)

| Event Emitted | Trigger | Listeners |
|--------------|---------|-----------|
| `suggested_po_created` | Auto-generated suggestion after SO | Teams |
| `suggested_po_approved` | User approves suggestion | Teams |
| `po_created` | PO actually submitted to vendor | Teams, Quality |

**Emitted Methods:**
- `nightly_recalc` cron → check lead times, emit `suggested_po_created`
- `stock_insufficient` listener → create suggestion, emit `suggested_po_created`

### 3.3 Teams (`ksf_FA_Teams`)

Teams is the primary event **listener**. It responds to events by creating tasks for the appropriate team.

| Event Listened | Action | Team Created |
|---------------|--------|-------------|
| `stock_insufficient` | Create followup task | Sales |
| `suggested_po_created` | Create purchasing task | Purchasing |
| `po_created` | Create receiving task | Warehouse |
| `grn_received` | Create AR task for shipment | AR |
| `so_created` with future delivery | Create followup task | Sales |
| `so_delivered` | Create AR task | AR |
| `quality_issue_created` | Create 8D task | Quality |

**Team Types (configured per-install):**
- `sales` — Sales followup
- `purchasing` — PO processing
- `warehouse` — Receiving/shipping
- `ar` — Accounts receivable
- `ap` — Accounts payable
- `quality` — Quality issues
- `hr` — Human resources

---

## 4. Core Workflows

### 4.1 JIT Ordering Workflow

```
ST_SALESORDER created
    │
    ├─► StockReservations: reserve stock
    │       └─► emit stock_reserved
    │               └─► Teams: create salesman followup task (if delivery_date is future)
    │
    ├─► StockReservations: check stock levels
    │       └─► if insufficient: emit stock_insufficient
    │               └─► SuggestedPO: check lead times, emit suggested_po_created
    │                       └─► Teams: create purchasing task
    │
    └─► SuggestedPO: calculate JIT suggestions (delivery_date - lead_time)
            └─► emit suggested_po_created (nightly cron)
                    └─► Teams: create purchasing task
```

### 4.2 PO Creation Workflow

```
suggested_po_created received by Teams
    └─► Teams: create purchasing task for buyer

User approves suggestion (or auto-submit via AI)
    └─► emit suggested_po_approved
            └─► SuggestedPO: create PO in FA
                    └─► emit po_created
                            ├─► Teams: create receiving task
                            ├─► Teams: create AP task (invoice expected)
                            └─► Quality: queue for QA check
```

### 4.3 Delivery/Fulfillment Workflow

```
ST_CUSTDELIVERY created (or stock decremented)
    └─► emit stock_released
            ├─► SuggestedPO: release any related suggestions
            └─► Teams: create AR task for invoicing

ST_SALESINVOICE created
    └─► emit so_invoiced
            └─► Teams: mark AR task complete, create collection followup if needed
```

---

## 5. Event Payload Schemas

### 5.1 `stock_reserved`

```php
$data = [
    'module'      => 'ksf_FA_StockReservations',
    'event'       => 'stock_reserved',
    'timestamp'   => '2024-01-15 14:30:00',
    'so_order_no' => 12345,
    'stock_id'    => 'SKU-001',
    'quantity'    => 10,
    'location'    => 'MAIN',
];
```

### 5.2 `stock_insufficient`

```php
$data = [
    'module'      => 'ksf_FA_StockReservations',
    'event'       => 'stock_insufficient',
    'timestamp'   => '2024-01-15 14:30:00',
    'so_order_no' => 12345,
    'items'       => [
        ['stock_id' => 'SKU-001', 'requested' => 10, 'available' => 3],
        ['stock_id' => 'SKU-002', 'requested' => 5, 'available' => 5],
    ],
];
```

### 5.3 `suggested_po_created`

```php
$data = [
    'module'         => 'ksf_FA_SuggestedPO',
    'event'          => 'suggested_po_created',
    'timestamp'      => '2024-01-15 14:30:00',
    'suggestion_id'  => 789,
    'supplier_id'   => 42,
    'items'          => [
        ['stock_id' => 'SKU-001', 'qty' => 100, 'unit_cost' => 5.99],
    ],
    'reason'         => 'lead_time_coverage',  // or 'stock_insufficient', 'moq_gap'
    'needed_by'      => '2024-02-15',          // Delivery date needed
    'order_by'       => '2024-02-01',          // Order date needed (needed_by - lead_time)
];
```

### 5.4 `po_created`

```php
$data = [
    'module'       => 'ksf_FA_SuggestedPO',
    'event'        => 'po_created',
    'timestamp'    => '2024-01-15 14:30:00',
    'po_number'    => 'PO/2024/00123',
    'supplier_id'  => 42,
    'items'        => [
        ['stock_id' => 'SKU-001', 'qty' => 100, 'unit_cost' => 5.99],
    ],
    'suggestion_id' => 789,  // If created from suggestion
    'created_by'    => 'system',  // or 'user_id'
];
```

### 5.5 `task_created`

```php
$data = [
    'module'       => 'ksf_FA_Teams',
    'event'        => 'task_created',
    'timestamp'    => '2024-01-15 14:30:00',
    'task_id'      => 456,
    'team_type'    => 'purchasing',  // sales, warehouse, ar, etc.
    'title'        => 'Review PO for SKU-001',
    'description'   => 'Suggested PO #789 needs approval',
    'due_date'     => '2024-02-01',
    'assigned_to'   => null,  // Team assignment, not individual
    'related'       => [
        'type' => 'suggested_po',
        'id'   => 789,
    ],
];
```

---

## 6. Module Implementation Checklist

When adding a new module:

### Emitter Checklist
- [ ] Identify which CRUD operations require events
- [ ] Emit events via `hook_invoke_all('{event_name}', $data)` after state changes
- [ ] Populate all standard payload fields (`module`, `event`, `timestamp`)
- [ ] Include relevant IDs and references for listeners
- [ ] Document emitted events in module's AGENTS_APPENDIX.md

### Listener Checklist
- [ ] Implement method named `{event_name}(array &$data)`
- [ ] Check `$data['event']` to confirm correct event
- [ ] Return early if data insufficient
- [ ] Use `class_exists()` guard before using other module's classes
- [ ] Log errors, don't throw (hook methods should be fault-tolerant)
- [ ] Document listened events in module's AGENTS_APPENDIX.md

---

## 7. Teams Configuration

Teams uses a config screen to enable/disable team types:

```php
// teams_config table
[
    'team_type'     => 'purchasing',
    'enabled'       => true,
    'default_assign' => 'purchasing_manager',  // Role or user
    ' SLA_hours'     => 24,
],
```

Modules that emit events don't check if Teams is installed or configured - they emit regardless. Teams' event listeners self-disable if their team type is not enabled.

---

## 8. Anti-Patterns to Avoid

### ❌ Don't emit from constructors or setters
```php
// BAD
function setQuantity($qty) {
    $this->quantity = $qty;
    hook_invoke_all('quantity_changed', $data);  // May fire on create, edit, read
}
```

### ✅ Emit from the workflow action method
```php
// GOOD
function handleSoCreation($soData) {
    $this->save($soData);
    hook_invoke_all('so_created', $data);  // Explicit workflow step
}
```

### ❌ Don't emit before state is committed
```php
// BAD
function createSo($data) {
    hook_invoke_all('so_created', $data);  // Before save!
    $this->save($data);
}
```

### ❌ Don't require a specific listener
```php
// BAD - tight coupling
if (class_exists('Ksfraser\FA\Teams\TaskService')) {
    $taskService->createTask(...);  // Directly coupled
}
```

### ✅ Emit and let listeners decide
```php
// GOOD - decoupled
hook_invoke_all('so_created', $data);  // Any module can listen
```

---

## 9. Future: AI Event Monitoring

The event system enables future AI monitoring:

```
Event stream ──► AI Event Monitor
                      │
                      ├─► If po_created + supplier.hasDiscountTerm:
                      │       └─► Auto-submit to vendor API
                      │
                      ├─► If stock_insufficient + recurring(3x):
                      │       └─► Alert: chronic shortage
                      │
                      └─► If so_delivered + delay > threshold:
                              └─► Alert: delivery SLA breach
```

---

*Document Version: 1.0.0*
*Created: 2024-01-15*
*Related: AGENTS_ARCH.md §11 Inter-module communication*