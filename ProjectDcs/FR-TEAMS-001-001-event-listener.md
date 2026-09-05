# FR-TEAMS-001-001 — Teams Module Event Listener System

## Purpose

Define how the Teams module listens to cross-module events and creates tasks automatically.

## Functional Requirement

### FR-TEAMS-001-001-001: Event Listener Registration

The Teams module shall register listeners for these events:

| Event | Team Type | Task Title Pattern |
|-------|-----------|-------------------|
| `stock_insufficient` | sales | "Follow up: Stock shortage for Order #%s" |
| `suggested_po_created` | purchasing | "Review Suggested PO #%s" |
| `suggested_po_approved` | purchasing | "Submit PO #%s to Vendor" |
| `po_created` | warehouse | "Expecting GRN for PO #%s" |
| `so_created` | sales | "Confirm Order #%s with customer" |
| `so_delivered` | ar | "Invoice Customer for Order #%s" |
| `grn_received` | warehouse | "Put Away: %s received" |
| `quality_issue_created` | quality | "Quality Issue: %s" |

### FR-TEAMS-001-001-002: Team Type Configuration

The Teams module shall provide a configuration screen allowing:
- Enable/disable each team type (sales, purchasing, warehouse, ar, ap, quality, hr)
- Set default SLA (hours) per team type
- Assign a default role/user per team type

### FR-TEAMS-001-001-003: Fault-Tolerant Listening

When an event is received:
1. Check if team type is enabled in config
2. If not enabled, return without action
3. If enabled, create task
4. On any error (DB, missing data), log and return (don't throw)

### FR-TEAMS-001-001-004: Task Creation Payload

When creating a task from an event:

```php
hook_invoke_all('task_created', [
    'module'       => 'ksf_FA_Teams',
    'event'        => 'task_created',
    'timestamp'    => date('Y-m-d H:i:s'),
    'team_type'    => $teamType,       // From config
    'title'        => $title,          // From event mapping
    'description'   => $description,    // Event summary
    'due_date'     => $dueDate,        // Calculated from SLA
    'related'      => [
        'type' => $relatedType,        // e.g. 'so', 'po', 'suggestion'
        'id'   => $relatedId,
    ],
]);
```

---

## Related Events Schema

### Incoming: `suggested_po_created`

```php
// Listened by Teams
$data = [
    'module'         => 'ksf_FA_SuggestedPO',
    'event'          => 'suggested_po_created',
    'suggestion_id'  => 789,
    'supplier_id'   => 42,
    'items'          => [...],
    'needed_by'      => '2024-02-15',
];

// Teams action: create purchasing task
```

### Incoming: `po_created`

```php
// Listened by Teams
$data = [
    'module'       => 'ksf_FA_SuggestedPO',
    'event'        => 'po_created',
    'po_number'    => 'PO/2024/00123',
    'supplier_id'  => 42,
    'suggestion_id' => 789,
];

// Teams action: create warehouse receiving task + AP task
```

---

## Acceptance Criteria

- [ ] Teams module responds to `suggested_po_created` when purchasing team enabled
- [ ] Teams module responds to `po_created` when warehouse team enabled
- [ ] Teams module ignores events for disabled team types
- [ ] Task creation errors are logged, not thrown
- [ ] Configuration screen allows enable/disable of team types

---

*BABOK Related: FR-TEAMS-001-001*
*Document Version: 1.0.0*