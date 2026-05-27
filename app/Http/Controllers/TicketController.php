<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Models\BusinessSetting;
use App\Models\Check;
use App\Models\KitchenStation;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\URL;

class TicketController extends Controller
{
    /**
     * Printable receipt for the full check (customer copy).
     */
    public function check(Check $check): View
    {
        $check->load([
            'table.area',
            'waiter',
            'items' => fn ($q) => $q->orderBy('created_at'),
            'items.modifiers',
            'items.kitchenStation:id,name',
            'felInvoice',
        ]);

        $ivaRate  = (float) config('restaurant.iva_rate');
        $business = BusinessSetting::instance();

        $ratingUrl = URL::temporarySignedRoute(
            'rate.show',
            now()->addDays(7),
            ['check' => $check->id],
        );

        $ratingQr = (new Builder(
            writer: new SvgWriter(),
            writerOptions: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
                SvgWriter::WRITER_OPTION_COMPACT                 => true,
            ],
            data:   $ratingUrl,
            size:   140,
            margin: 4,
        ))->build()->getString();

        return view('tickets.check', compact('check', 'ivaRate', 'business', 'ratingQr'));
    }

    /**
     * Printable kitchen ticket for a station — shows all pending items.
     */
    public function station(KitchenStation $station): View
    {
        $items = $station->checkItems()
            ->whereIn('status', [
                CheckItemStatus::Ordered->value,
                CheckItemStatus::Preparing->value,
            ])
            ->with([
                'check.table.area',
                'modifiers',
            ])
            ->orderBy('sent_at')
            ->get();

        return view('tickets.station', compact('station', 'items'));
    }
}
