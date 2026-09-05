# FR-SR-001-001 — StockReservations Event Emission

## Purpose

Define how StockReservations emits events for stock reservation changes.

## Functional Requirement

### FR-SR-001-001-001: Emit `stock_reserved` on SO Creation

When a sales order is created and stock is successfully reserved:

1. Insert reservation into `stock_reservations` table
2. Decrement available stock (or flag for MRP)
3. Emit `stock_reserved`

```php
function db_postwrite($cart, $trans_type)
{
    if ($trans_type != ST_SALESORDER) {
        return;
    }

    $orderNo = is_object($cart) ? $cart->order_no : 0;
    if ($orderNo <= 0) {
        return;
    }

    $reserved = $this->reserveStock($cart);

    if (!empty($reserved)) {
        $data = [
            'module'      => 'ksf_FA_StockReservations',
            'event'       => 'stock_reserved',
            'timestamp'    => date('Y-m-d H:i:s'),
            'so_order_no'  => $orderNo,
            'items'        => $reserved,
        ];

        hook_invoke_all('stock_reserved', $data);
    }
}
```

### FR-SR-001-001-002: Emit `stock_insufficient` on Stock Check Failure

When stock check fails during SO creation:

```php
function stock_reservation_insufficient(array &$data): void
{
    // Called when SO creation fails stock check

    $data = [
        'module'      => 'ksf_FA_StockReservations',
        'event'       => 'stock_insufficient',
        'timestamp'    => date('Y-m-d H:i:s'),
        'so_order_no'  => $data['order_no'] ?? 0,
        'items'        => $data['items'] ?? [],  // ['stock_id', 'requested', 'available']
    ];

    hook_invoke_all('stock_insufficient', $data);
}
```

### FR-SR-001-001-003: Emit `stock_released` on SO Void/Cancel/Delivery

When a reservation is released:

```php
function db_prevoid($trans_type, $trans_no)
{
    if ($trans_type != ST_SALESORDER) {
        return;
    }

    $released = $this->releaseReservations($trans_no);

    if (!empty($released)) {
        $data = [
            'module'       => 'ksf_FA_StockReservations',
            'event'        => 'stock_released',
            'timestamp'     => date('Y-m-d H:i:s'),
            'so_order_no'   => $trans_no,
            'items'         => $released,
            'reason'        => 'voided',
        ];

        hook_invoke_all('stock_released', $data);
    }
}
```

Also emit on `ST_CUSTDELIVERY` (delivery completes reservation):

```php
function db_postwrite($cart, $trans_type)
{
    if ($trans_type == ST_CUSTDELIVERY) {
        $released = $this->releaseReservationsForDelivery($cart);
        if (!empty($released)) {
            $data = [
                'module'       => 'ksf_FA_StockReservations',
                'event'        => 'stock_released',
                'timestamp'     => date('Y-m-d H:i:s'),
                'delivery_no'  => $cart->delivery_number,
                'items'        => $released,
                'reason'       => 'delivered',
            ];
            hook_invoke_all('stock_released', $data);
        }
    }
}
```

---

## Event Payloads

### `stock_reserved`

```php
[
    'module'      => 'ksf_FA_StockReservations',
    'event'       => 'stock_reserved',
    'timestamp'    => '2024-01-15 14:30:00',
    'so_order_no'  => 12345,
    'items'        => [
        ['stock_id' => 'SKU-001', 'quantity' => 10, 'location' => 'MAIN'],
        ['stock_id' => 'SKU-002', 'quantity' => 5, 'location' => 'MAIN'],
    ],
]
```

### `stock_insufficient`

```php
[
    'module'      => 'ksf_FA_StockReservations',
    'event'       => 'stock_insufficient',
    'timestamp'    => '2024-01-15 14:30:00',
    'so_order_no'  => 12345,
    'items'        => [
        ['stock_id' => 'SKU-001', 'requested' => 10, 'available' => 3],
    ],
]
```

### `stock_released`

```php
[
    'module'       => 'ksf_FA_StockReservations',
    'event'        => 'stock_released',
    'timestamp'    => '2024-01-15 14:30:00',
    'so_order_no'   => 12345,
    'items'        => [
        ['stock_id' => 'SKU-001', 'quantity' => 10],
    ],
    'reason'       => 'voided',  // or 'delivered', 'cancelled'
]
```

---

## Acceptance Criteria

- [ ] `db_postwrite` for ST_SALESORDER emits `stock_reserved` when stock reserved
- [ ] `db_prevoid` for ST_SALESORDER emits `stock_released` when voided
- [ ] `db_postwrite` for ST_CUSTDELIVERY emits `stock_released` when delivered
- [ ] All events include required fields (module, event, timestamp)
- [ ] Events emitted with complete item details for listeners
- [ ] `stock_insufficient` emitted when SO creation fails stock check

---

*BABOK Related: FR-SR-001-001*
*Document Version: 1.0.0*