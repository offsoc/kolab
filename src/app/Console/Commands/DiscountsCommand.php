<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

/**
 * List discounts.
 *
 * Example usage:
 *
 * ```
 * $ ./artisan discounts
 * 003f18e5-cbd2-4de8-9485-b0c966e4757d
 * 00603496-5c91-4347-b341-cd5022566210
 * 0076b174-f122-458a-8466-bd05c3cac35d
 * (...)
 * ```
 *
 * To include specific attributes, use `--attr` (allowed multiple times):
 *
 * ```
 * $ ./artisan discounts --attr=discount --attr=description
 * 003f18e5-cbd2-4de8-9485-b0c966e4757d 54 Custom volume discount
 * 00603496-5c91-4347-b341-cd5022566210 30 Developer Discount
 * 0076b174-f122-458a-8466-bd05c3cac35d 100 it's me
 * (...)
 * ```
 */
class DiscountsCommand extends ObjectListCommand
{
    protected $objectClass = \App\Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle = null;
}
