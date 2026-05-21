<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket cocina — {{ $station->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 13px; color: #000; background: #fff; }
        .wrap { max-width: 320px; margin: 0 auto; padding: 16px; }

        h1 { font-size: 16px; text-align: center; margin-bottom: 2px; }
        .sub { font-size: 11px; text-align: center; color: #555; margin-bottom: 12px; }
        .divider { border: none; border-top: 1px dashed #000; margin: 10px 0; }

        .ticket { margin-bottom: 16px; }
        .ticket-header { font-size: 12px; font-weight: bold; margin-bottom: 4px; }
        .ticket-table { font-size: 12px; color: #444; }

        .item { display: flex; gap: 8px; padding: 2px 0; }
        .item-qty { font-weight: bold; min-width: 24px; }
        .item-name {}
        .modifier { font-size: 11px; padding-left: 32px; color: #555; }

        @media print {
            body { margin: 0; }
            @page { margin: 0; size: 80mm auto; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>COCINA — {{ strtoupper($station->name) }}</h1>
    <div class="sub">{{ now()->format('d/m/Y H:i') }}</div>

    @if ($items->isEmpty())
        <p style="text-align:center; color:#555; margin-top:20px">Sin pedidos pendientes</p>
    @else
        @foreach ($items->groupBy('check_id') as $checkId => $checkItems)
            @php $first = $checkItems->first(); @endphp
            <div class="ticket">
                <hr class="divider">
                <div class="ticket-header">
                    {{ $first->check->number }}
                    · {{ $first->check->table?->area?->name }}
                    · {{ $first->check->table?->name ?? '—' }}
                </div>
                @foreach ($checkItems as $item)
                    <div class="item">
                        <span class="item-qty">{{ $item->quantity }}×</span>
                        <span class="item-name">{{ $item->name_snapshot }}</span>
                    </div>
                    @foreach ($item->modifiers as $mod)
                        <div class="modifier">+ {{ $mod->name_snapshot }}</div>
                    @endforeach
                    @if ($item->notes)
                        <div class="modifier">* {{ $item->notes }}</div>
                    @endif
                @endforeach
            </div>
        @endforeach
    @endif
</div>
<script>window.onload = () => window.print();</script>
</body>
</html>
