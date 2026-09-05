# FR-SPO-001-001 — SuggestedPO Event Emission

## Purpose

Define how SuggestedPO emits events for PO creation and related actions.

## Functional Requirement

### FR-SPO-001-001-001: Event Emission on Suggestion Creation

When SuggestedPO creates a new suggestion (via `stock_insufficient` hook or `nightly_recalc`):

1. Create suggestion in `suggested_orders` table
2. Calculate `needed_by` (from SO delivery date or calculated)
3. Calculate `order_by` (`needed_by - supplier.lead_time_days`)
4. Emit `suggested_po_created`

```php
function stock_insufficient(array &$data): void
{
    // ... calculate suggestions ...

    foreach ($suggestions as $suggestion) {
        $suggestionId = $this->saveSuggestion($suggestion);

        $data = [
            'module'         => 'ksf_FA_SuggestedPO',
            'event'          => 'suggested_po_created',
            'timestamp'      => date('Y-m-d H:i:s'),
            'suggestion_id'  => $suggestionId,
            'supplier_id'    => $suggestion['supplier_id'],
            'items'          => $suggestion['items'],
            'reason'         => 'stock_insufficient',
            'needed_by'      => $suggestion['needed_by'],
            'order_by'       => $suggestion['order_by'],
        ];

        hook_invoke_all('suggested_po_created', $data);
    }
}
```

### FR-SPO-001-001-002: Event Emission on Approval

When user approves a suggestion:

1. Update suggestion status to `approved`
2. Emit `suggested_po_approved`

```php
function approveSuggestion(int $suggestionId): bool
{
    $this->updateStatus($suggestionId, 'approved');

    $suggestion = $this->getSuggestion($suggestionId);

    $data = [
        'module'         => 'ksf_FA_SuggestedPO',
        'event'          => 'suggested_po_approved',
        'timestamp'      => date('Y-m-d H:i:s'),
        'suggestion_id'  => $suggestionId,
        'supplier_id'    => $suggestion['supplier_id'],
        'approved_by'    => get_current_user(),
    ];

    hook_invoke_all('suggested_po_approved', $data);

    return true;
}
```

### FR-SPO-001-001-003: Event Emission on PO Creation

When suggestion is converted to actual PO:

1. Create PO in FA via standard FA API
2. Update suggestion status to `converted`
3. Emit `po_created`

```php
function convertToPO(int $suggestionId): ?string
{
    $poNumber = $this->createPOInFA($suggestionId);
    if ($poNumber === null) {
        return null;
    }

    $this->updateStatus($suggestionId, 'converted', $poNumber);

    $suggestion = $this->getSuggestion($suggestionId);

    $data = [
        'module'       => 'ksf_FA_SuggestedPO',
        'event'        => 'po_created',
        'timestamp'    => date('Y-m-d H:i:s'),
        'po_number'    => $poNumber,
        'supplier_id'  => $suggestion['supplier_id'],
        'suggestion_id' => $suggestionId,
        'created_by'   => get_current_user(),
    ];

    hook_invoke_all('po_created', $data);

    return $poNumber;
}
```

### FR-SPO-001-001-004: Nightly Recalculation

The `nightly_recalc` hook shall:
1. Check all pending suggestions
2. Expire outdated suggestions (delivery date passed)
3. Create new suggestions based on stock levels + lead times
4. Emit `suggested_po_created` for any new suggestions

---

## Event Payloads

### `suggested_po_created`

```php
[
    'module'         => 'ksf_FA_SuggestedPO',
    'event'          => 'suggested_po_created',
    'timestamp'      => '2024-01-15 14:30:00',
    'suggestion_id'  => 789,
    'supplier_id'   => 42,
    'items'          => [
        ['stock_id' => 'SKU-001', 'qty' => 100, 'unit_cost' => 5.99],
    ],
    'reason'         => 'stock_insufficient',  // or 'lead_time_coverage', 'moq_gap'
    'needed_by'      => '2024-02-15',
    'order_by'       => '2024-02-01',
]
```

### `suggested_po_approved`

```php
[
    'module'         => 'ksf_FA_SuggestedPO',
    'event'          => 'suggested_po_approved',
    'timestamp'      => '2024-01-15 14:30:00',
    'suggestion_id'  => 789,
    'supplier_id'   => 42,
    'approved_by'    => 15,  // user_id
]
```

### `po_created`

```php
[
    'module'       => 'ksf_FA_SuggestedPO',
    'event'        => 'po_created',
    'timestamp'    => '2024-01-15 14:30:00',
    'po_number'    => 'PO/2024/00123',
    'supplier_id'  => 42,
    'suggestion_id' => 789,
    'created_by'   => 'system',  // or user_id
]
```

---

## Acceptance Criteria

- [ ] `stock_insufficient` listener creates suggestion and emits `suggested_po_created`
- [ ] `nightly_recalc` creates suggestions and emits `suggested_po_created`
- [ ] Approval emits `suggested_po_approved`
- [ ] PO creation emits `po_created` with valid PO number
- [ ] All events include required fields (module, event, timestamp)
- [ ] Events emitted even if no listeners (fire-and-forget)

---

*BABOK Related: FR-SPO-001-001*
*Document Version: 1.0.0*