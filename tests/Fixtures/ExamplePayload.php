<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Tests\Fixtures;

/**
 * The canonical §4 example payload from the Atlas Core Order Bridge API
 * specification (version 1.3.4), with corrected arithmetic values.
 *
 * Corrections applied vs. the raw spec document:
 * - ancillary.total_ex_vat: "3.9990" → "34.9900" (formula: qty × unit_price = 1 × 34.9900)
 *   The spec changelog (1.3.4) noted arithmetic corrections; the raw document still
 *   contained the stale value. The correct value is confirmed by totals.ancillaries_net.
 */
final class ExamplePayload
{
    /** @return array<string, mixed> */
    public static function valid(): array
    {
        return [
            'schema_version' => '1.4.1',
            'generated_at' => '2026-03-21T10:45:00Z',
            'transfer_id' => '7322b271-e5e2-42d9-a42e-e4c4fb45a3f8',
            'trigger' => 'realtime',
            'source' => [
                'type' => 'cassa',
                'site_id' => 'site_richmond',
                'site_name' => 'The Palm Centre',
                'operator_id' => '1',
            ],
            'orders' => [
                [
                    'origin_ref' => 'CASSA-2026-00412',
                    'status' => 'pos',
                    'channel' => 'in-store',
                    'operator_id' => 'usr_042',
                    'ordered_at' => '2026-03-21T10:44:11Z',
                    'notes' => [
                        [
                            'type' => 'customer',
                            'note' => 'Customer requested care sheet',
                            'created_by' => 'usr_042',
                            'created_at' => '2026-03-21T10:44:11Z',
                        ],
                    ],
                    'customer' => [
                        'type' => 'registered',
                        'id' => 'cust_1089',
                    ],
                    'fulfilment' => [
                        'type' => 'collection',
                        'notes' => 'Collecting Friday afternoon',
                    ],
                    'items' => [
                        [
                            'sku' => 'TRF-45L',
                            'name' => 'Trachycarpus fortunei 45L',
                            'qty' => 2,
                            'uom' => 'item',
                            'unit_price' => '42.6100',
                            'discount' => '10.000000',
                            'vat_rate' => '0.00',
                            'batch' => 'BATCH-2024-TF-009',
                            'passport' => 'GB-12345-A',
                            'note' => [
                                'type' => 'ops',
                                'note' => 'item is located somewhere else',
                                'created_by' => 'usr_042',
                                'created_at' => '2026-03-21T10:44:11Z',
                            ],
                            'line_ex_vat' => '76.6980',
                            'line_vat' => '0.0000',
                        ],
                    ],
                    'ancillaries' => [
                        [
                            'category' => 'delivery_fee',
                            'qty' => 1,
                            'unit_price' => '34.9900',
                            'vat_rate' => '21.00',
                            'total_ex_vat' => '34.9900',
                            'total_vat' => '7.3500',
                        ],
                    ],
                    'totals' => [
                        'items_net' => '76.6980',
                        'ancillaries_net' => '34.9900',
                        'total_vat' => '7.3500',
                        'grand_total' => '119.0380',
                        'refund_of' => null,
                    ],
                    'payments' => [
                        [
                            'method' => 'card',
                            'amount' => '119.0380',
                            'reference' => 'TXN-STRIPE-89234',
                            'date' => '2026-03-21T10:44:11Z',
                        ],
                    ],
                ],
            ],
        ];
    }
}
