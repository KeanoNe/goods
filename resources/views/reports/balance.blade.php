<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bestandsbilanz</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .kopf { margin-bottom: 16px; }
        .kopf div { margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; border-bottom: 1px solid #333; padding: 4px; font-size: 9px; text-transform: uppercase; }
        td { padding: 4px; border-bottom: 1px solid #ddd; }
        .rechts { text-align: right; }
        .zwischensumme td { background: #f3f3f3; font-weight: bold; border-bottom: 1px solid #bbb; }
        .gesamtsumme td { border-top: 2px solid #333; border-bottom: none; font-weight: bold; font-size: 11px; }
        .unterschrift { margin-top: 60px; width: 100%; }
        .unterschrift td { border: none; padding-top: 4px; }
        .linie { border-top: 1px solid #333; width: 220px; }
        .leer { text-align: center; padding: 20px; color: #666; }
        h2 { font-size: 13px; margin: 28px 0 2px; }
        .hinweis { font-size: 9px; color: #555; margin-bottom: 10px; }
        .fussnote td { border: none; font-size: 9px; color: #555; padding-top: 2px; }
        .rest td { color: #555; }
    </style>
</head>
<body>
    <div class="kopf">
        <h1>Bestandsbilanz</h1>
        <div>Zeitraum: {{ $from->format('d.m.Y') }} &ndash; {{ $to->format('d.m.Y') }}</div>
        <div>Erstellt am: {{ now()->format('d.m.Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>Bezeichnung</th>
                <th>Lieferant</th>
                <th class="rechts">Menge</th>
                <th class="rechts">St&uuml;ckpreis</th>
                <th class="rechts">Gesamtwert</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($articles as $artikel)
                @foreach ($artikel['rows'] as $index => $zeile)
                    <tr>
                        <td>{{ $index === 0 ? $artikel['sku'] : '' }}</td>
                        <td>{{ $index === 0 ? $artikel['name'] : '' }}</td>
                        <td>{{ $zeile['supplier'] }}</td>
                        <td class="rechts">{{ $zeile['quantity'] }}</td>
                        <td class="rechts">{{ number_format($zeile['unit_price'], 2, ',', '.') }} &euro;</td>
                        <td class="rechts">{{ number_format($zeile['total'], 2, ',', '.') }} &euro;</td>
                    </tr>
                @endforeach
                <tr class="zwischensumme">
                    <td colspan="5" class="rechts">Zwischensumme {{ $artikel['name'] }}</td>
                    <td class="rechts">{{ number_format($artikel['subtotal'], 2, ',', '.') }} &euro;</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="leer">Im gew&auml;hlten Zeitraum gibt es keine Bestandsbewegungen</td>
                </tr>
            @endforelse
            <tr class="gesamtsumme">
                <td colspan="5" class="rechts">Gesamtsumme</td>
                <td class="rechts">{{ number_format($grand_total, 2, ',', '.') }} &euro;</td>
            </tr>
        </tbody>
    </table>

    <h2>Bestandswert zum {{ $bestandswert['stichtag']->format('d.m.Y') }}</h2>
    <div class="hinweis">Momentaufnahme des Lagers, zeitraumunabh&auml;ngig.</div>

    <table>
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>Bezeichnung</th>
                <th>Lieferant</th>
                <th class="rechts">Menge</th>
                <th class="rechts">St&uuml;ckpreis</th>
                <th class="rechts">Wert</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bestandswert['articles'] as $artikel)
                @foreach ($artikel['rows'] as $index => $zeile)
                    <tr @class(['rest' => $zeile['ohne_lieferant']])>
                        <td>{{ $index === 0 ? $artikel['sku'] : '' }}</td>
                        <td>{{ $index === 0 ? $artikel['name'] : '' }}@if ($index === 0 && $artikel['geloescht']) (gel&ouml;scht)@endif</td>
                        <td>{{ $zeile['supplier'] }}</td>
                        <td class="rechts">{{ $zeile['quantity'] }}</td>
                        <td class="rechts">{{ number_format($zeile['unit_price'], 2, ',', '.') }} &euro;</td>
                        <td class="rechts">{{ number_format($zeile['total'], 2, ',', '.') }} &euro;</td>
                    </tr>
                @endforeach
                <tr class="zwischensumme">
                    <td colspan="5" class="rechts">Zwischensumme {{ $artikel['name'] }}</td>
                    <td class="rechts">{{ number_format($artikel['subtotal'], 2, ',', '.') }} &euro;</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="leer">Derzeit liegt kein Bestand im Lager</td>
                </tr>
            @endforelse
            <tr class="gesamtsumme">
                <td colspan="5" class="rechts">Gesamtwert des Lagers</td>
                <td class="rechts">{{ number_format($bestandswert['grand_total'], 2, ',', '.') }} &euro;</td>
            </tr>
            @if ($bestandswert['ohne_preis_artikel'] > 0)
                <tr class="fussnote">
                    <td colspan="6" class="rechts">
                        F&uuml;r {{ $bestandswert['ohne_preis_artikel'] }}
                        von {{ $bestandswert['artikel_gesamt'] }} Artikeln
                        ({{ number_format($bestandswert['ohne_preis_menge'], 0, ',', '.') }} St&uuml;ck)
                        ist kein Preis hinterlegt; der Gesamtwert ist insoweit unvollst&auml;ndig.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="unterschrift">
        <tr>
            <td><div class="linie"></div></td>
            <td><div class="linie"></div></td>
        </tr>
        <tr>
            <td>Ort, Datum</td>
            <td>Unterschrift</td>
        </tr>
    </table>
</body>
</html>
