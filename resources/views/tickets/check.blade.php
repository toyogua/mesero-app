<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $check->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; background: #fff; }
        .wrap { max-width: 320px; margin: 0 auto; padding: 16px; }

        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        .sub { font-size: 11px; text-align: center; color: #555; margin-bottom: 12px; }
        .divider { border: none; border-top: 1px dashed #000; margin: 10px 0; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; padding-bottom: 4px; }
        th.r, td.r { text-align: right; }
        td { padding: 3px 0; vertical-align: top; }
        td.qty { width: 28px; }
        td.name { width: 55%; }
        td.unit { width: 20%; text-align: right; }
        td.total { text-align: right; }

        .modifier { font-size: 10px; color: #444; padding-left: 4px; }

        .totals td { padding: 2px 0; }
        .totals .label { color: #555; }
        .totals .grand { font-size: 14px; font-weight: bold; border-top: 1px solid #000; padding-top: 6px; }

        .footer { font-size: 10px; text-align: center; margin-top: 14px; color: #777; }

        @media print {
            body { margin: 0; }
            @page { margin: 0; size: 80mm auto; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ config('app.name', 'mesero-app') }}</h1>
    <div class="sub">
        Ticket {{ $check->number }}<br>
        {{ $check->table?->area?->name }} · {{ $check->table?->name ?? 'Mesa libre' }}<br>
        {{ $check->covers }} comensal{{ $check->covers != 1 ? 'es' : '' }} ·
        Mesero: {{ $check->waiter->name }}<br>
        {{ $check->opened_at?->format('d/m/Y H:i') }}
    </div>

    <hr class="divider">

    <table>
        <thead>
            <tr>
                <th class="qty">Cant</th>
                <th class="name">Descripción</th>
                <th class="unit">Unit</th>
                <th class="total r">Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($check->items->where('status.value', '!=', 'cancelled') as $item)
            @php
                $modTotal = $item->modifiers->sum('price_snapshot');
                $unitPrice = (float)$item->price_snapshot + $modTotal;
                $lineTotal = $unitPrice * $item->quantity;
            @endphp
            <tr>
                <td class="qty">{{ $item->quantity }}</td>
                <td class="name">{{ $item->name_snapshot }}</td>
                <td class="unit">Q {{ number_format($unitPrice, 2) }}</td>
                <td class="total">Q {{ number_format($lineTotal, 2) }}</td>
            </tr>
            @foreach ($item->modifiers as $mod)
            <tr>
                <td></td>
                <td class="modifier" colspan="3">
                    + {{ $mod->name_snapshot }}
                    @if ($mod->price_snapshot > 0)
                        (Q {{ number_format($mod->price_snapshot, 2) }})
                    @endif
                </td>
            </tr>
            @endforeach
            @if ($item->notes)
            <tr>
                <td></td>
                <td class="modifier" colspan="3">* {{ $item->notes }}</td>
            </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <hr class="divider">

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="r">Q {{ number_format($check->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="label">IVA ({{ number_format($ivaRate * 100, 0) }}%)</td>
            <td class="r">Q {{ number_format($check->tax, 2) }}</td>
        </tr>
        @if ($check->tip > 0)
        <tr>
            <td class="label">Propina</td>
            <td class="r">Q {{ number_format($check->tip, 2) }}</td>
        </tr>
        @endif
        <tr class="grand">
            <td><strong>TOTAL</strong></td>
            <td class="r"><strong>Q {{ number_format($check->total, 2) }}</strong></td>
        </tr>
    </table>

    <div class="footer">¡Gracias por su visita!</div>
</div>
<script>window.onload = () => window.print();</script>
</body>
</html>
