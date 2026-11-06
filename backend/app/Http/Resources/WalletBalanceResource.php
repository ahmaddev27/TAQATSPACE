<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WalletEntry;
use App\Support\Finance\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One customer's wallet in the list: an aggregate row of WalletRepository::paginateBalances
 * (customer_id, balance = SUM(amount), last_entry_at = MAX(created_at), customer_name).
 *
 * @mixin WalletEntry
 */
class WalletBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $last = $this->resource->getAttribute('last_entry_at');

        return [
            'customer' => ['id' => $this->customer_id, 'name' => $this->resource->getAttribute('customer_name')],
            'balance' => Decimal::of($this->resource->getAttribute('balance')),
            // ISO-8601 in UTC: a bare "Y-m-d H:i:s" is read as local time, differently on the server and in the browser.
            'last_entry_at' => $last === null ? null : Carbon::parse((string) $last, (string) config('app.timezone'))->utc()->toIso8601String(),
        ];
    }
}
