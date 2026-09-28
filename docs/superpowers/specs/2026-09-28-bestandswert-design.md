# Bestandswert: die Bilanz um den Wert des vorhandenen Lagers ergänzen

Datum: 2026-09-28

## Ziel

Die Bestandsbilanz endet bisher mit den Bewegungen eines Zeitraums. Sie soll
zusätzlich ausweisen, wie viele Einheiten je Artikel noch vorhanden sind und
was sie wert sind, damit am Ende eine Summe über das gesamte Lager steht.

Der Bestand trägt keinen Lieferanten: `stocks` kennt nur Artikel, Lagerplatz
und Menge. Ein Lieferant hängt ausschließlich an einzelnen Bewegungen. Die
Aufschlüsselung nach Lieferant und Preis wird deshalb aus dem
Bewegungsjournal hergeleitet.

## Ausgangslage

Erhoben am 2026-09-28 auf dem Produktionssystem:

| Kennzahl | Wert |
| --- | --- |
| Artikel mit Bestand > 0 | 208 |
| Gesamtbestand | 81.163 Stück |
| Artikel auf mehreren Lagerplätzen | 59 |
| Artikel mit negativem Bestand | 0 |
| Soft-gelöschte Artikel mit Bestand > 0 | 2 |
| Lieferanten angelegt | 0 |
| Artikel mit Lieferant und Preis | 0 |
| Bewegungen mit `supplier_id` oder `unit_price` | 0 von 3.940 |

Daraus folgt: am Tag der Einführung ist der gesamte Bestand nicht zuordenbar
und landet vollständig in der Restzeile. Die Aufschlüsselung wächst erst mit
künftigen Buchungen.

## Der neue Abschnitt

Unter dem bestehenden Bewegungsteil, in allen drei Ausgaben — Vorschau, PDF
und XLSX.

Überschrift: **Bestandswert zum <Erstellungsdatum> (zeitraumunabhängig)**

Der Zusatz ist wichtig: Der Bestandswert ist eine Momentaufnahme und hängt
nicht am gewählten Zeitraum. Wer einen einzelnen Tag auswertet, sieht oben
drei Bewegungen und unten das vollständige Lager.

### Spalten

| Artikelnummer | Bezeichnung | Lieferant | Menge | Stückpreis | Wert |
| --- | --- | --- | --- | --- | --- |

Je Artikel ein Block aus einer oder mehreren Zeilen, darunter eine
Zwischensumme. Artikelnummer und Bezeichnung stehen nur in der ersten Zeile
eines Blocks, wie im Bewegungsteil.

Ganz unten die Gesamtsumme über alle Artikel.

## Berechnung

### Menge je Artikel

Summe aus `stocks.quantity` über alle Lagerplätze. Nur Artikel mit einer
Summe größer null. Soft-gelöschte Artikel erscheinen mit, wenn sie Bestand
führen — die Ware liegt physisch im Regal —, mit dem Zusatz `(gelöscht)`
hinter der Bezeichnung.

### Aufschlüsselung nach Lieferant und Preis

Verbrauchsannahme: **FIFO**. Die älteste Ware verlässt das Lager zuerst, es
bleiben also die jüngsten Zugänge liegen.

Je Artikel:

1. Den vorhandenen Bestand als zu deckende Restmenge setzen.
2. Die Eingangsbuchungen (`type = 'in'`) mit gesetztem `supplier_id` von der
   jüngsten zur ältesten durchgehen. `created_at` absteigend, bei
   Gleichstand `id` absteigend.
3. Von jeder Buchung so viel der Restmenge zuordnen, wie ihre Menge hergibt,
   höchstens aber die noch offene Restmenge.
4. Abbrechen, sobald die Restmenge null ist.
5. Die zugeordneten Mengen nach Lieferant und `unit_price` gruppieren. Jede
   Gruppe ergibt eine Zeile, bewertet mit ihrem `unit_price` — dem Preis,
   der beim Buchen tatsächlich galt.

Bleibt nach Schritt 4 Restmenge übrig, entsteht eine abgesetzte Zeile mit
dieser Menge. Ihre Beschriftung nennt die Herkunft des Preises, damit ein
Leser des Ausdrucks nicht rätseln muss:

- mit Standard-Lieferant: `Ohne Lieferant (Standardpreis <Lieferantenname>)`
- ohne Standard-Lieferant: `Ohne Lieferant (kein Preis hinterlegt)`

### Bewertung der Restzeile

Die Restmenge wird mit dem aktuellen Stückpreis des als Standard markierten
Lieferanten des Artikels bewertet (`article_supplier` mit `is_default = 1`).

Hat der Artikel keinen Standard-Lieferanten, ist der Preis unbekannt.
Stückpreis und Wert stehen dann auf `0,00 €` — die Spalten bleiben
durchgängig numerisch, damit in Excel gerechnet werden kann. Das entspricht
der Entscheidung, die im Bewegungsteil für Buchungen ohne Lieferant bereits
getroffen wurde.

### Summen

Zwischensumme je Artikel: Summe der Werte seiner Zeilen.
Gesamtsumme: Summe aller Zwischensummen.

### Die Fußzeile

Unter der Gesamtsumme steht, wie viel davon unbewertet blieb:

```
Gesamtwert des Lagers                                        0,00 €
davon ohne hinterlegten Preis: 208 von 208 Artikeln (81.163 Stück)
```

Die Zahlen stammen aus `ohne_preis_artikel`, `artikel_gesamt` und
`ohne_preis_menge`.

Ohne diese Zeile wäre eine Null die stille Behauptung, das Lager sei nichts
wert. Mit ihr ist sie eine nachvollziehbare Aussage. Die Zahl schrumpft von
selbst, sobald Lieferantenpreise gepflegt werden. Sie entfällt, wenn alle
Artikel einen Preis haben.

## Aufbau

Ein eigener `App\Services\BestandswertService` mit einer öffentlichen
Methode. Der bestehende `BalanceReportService` beantwortet „was hat sich in
einem Zeitraum bewegt", der neue „was liegt jetzt da und was ist es wert" —
zwei verschiedene Fragen mit verschiedenen Datenquellen, getrennt testbar.

`BalanceReportController` bekommt beide injiziert und reicht beide Ergebnisse
an Vorschau, PDF und XLSX weiter.

### Rückgabeform

```php
array{
    stichtag: CarbonInterface,
    articles: list<array{
        article_id: int,
        sku: string,
        name: string,
        geloescht: bool,
        rows: list<array{
            supplier: string,
            quantity: int,
            unit_price: float,
            total: float,
            ohne_lieferant: bool
        }>,
        subtotal: float
    }>,
    grand_total: float,
    ohne_preis_artikel: int,
    ohne_preis_menge: int,
    artikel_gesamt: int
}
```

Die drei Zähler sind genau so definiert:

- `artikel_gesamt` — Zahl der Artikel in diesem Abschnitt, also die mit
  Bestand über null. Nicht die Zahl aller Artikel im System.
- `ohne_preis_artikel` — davon jene, die eine Restzeile haben **und** keinen
  Standard-Lieferanten, deren Restmenge also mit 0 € eingeht. Ein Artikel,
  dessen Bestand sich vollständig Zugängen zuordnen ließ, zählt nicht mit,
  auch wenn er keinen Standard-Lieferanten hat.
- `ohne_preis_menge` — die Summe der Restmengen genau dieser Artikel.

Artikel sind nach `sku` sortiert. Innerhalb eines Artikels stehen die
zugeordneten Zeilen zuerst, nach Lieferantenname und dann Stückpreis
sortiert; die Zeile `Ohne Lieferant` steht immer zuletzt.

### Abfragen

Drei Abfragen, unabhängig von der Artikelzahl:

1. Bestand je Artikel aus `stocks`, gruppiert nach `article_id`.
2. Stammdaten der betroffenen Artikel, `withTrashed()`.
3. Alle Eingangsbuchungen mit `supplier_id` für diese Artikel, mit
   Lieferantennamen verknüpft, absteigend sortiert. Die Zuordnung nach FIFO
   geschieht danach in PHP.

Dazu eine vierte für die Standardpreise aus `article_supplier`.

Kein N+1: die Zuordnung läuft über bereits geladene Sammlungen.

## Nicht Teil dieser Spezifikation

- Eine andere Verbrauchsannahme als FIFO. LIFO oder gleitender Durchschnitt
  wären eigene Entscheidungen mit eigenem Aufwand.
- Das Nachtragen von Lieferanten oder Preisen an bestehenden Bewegungen. Die
  Information existiert nicht und lässt sich nicht rekonstruieren.
- Eine Bestandsbewertung zu einem zurückliegenden Stichtag. Der Bestandswert
  gilt immer zum Erstellungszeitpunkt.
- Änderungen am Bewegungsteil der Bilanz.

## Tests

Alle Tests mit `RefreshDatabase` gegen die MySQL-Datenbank `goods_test`.

- **`BestandswertServiceTest`**
  - Ein Artikel auf mehreren Lagerplätzen erscheint als ein Block mit der
    Gesamtmenge.
  - Ohne jede Buchung mit Lieferant besteht der Block aus einer einzigen
    Zeile `Ohne Lieferant` über den vollen Bestand.
  - Mit Eingangsbuchungen zweier Lieferanten zu verschiedenen Preisen
    entstehen zwei Zeilen mit den richtigen Mengen und Preisen.
  - FIFO greift: liegen mehr Zugänge vor als Bestand vorhanden ist, werden
    die jüngsten zugeordnet und die älteren ignoriert.
  - Deckt die Summe der Zugänge den Bestand nicht, entsteht zusätzlich eine
    Restzeile über die Differenz.
  - Die Restzeile wird mit dem Preis des Standard-Lieferanten bewertet.
  - Ohne Standard-Lieferant stehen Stückpreis und Wert der Restzeile auf
    `0.0`.
  - `ohne_preis_artikel` und `ohne_preis_menge` zählen genau die Artikel
    ohne Standardpreis und deren nicht zuordenbare Menge.
  - Ein soft-gelöschter Artikel mit Bestand erscheint mit `geloescht = true`.
  - Artikel ohne Bestand erscheinen nicht.
  - `Menge × Stückpreis` ergibt in jeder Zeile exakt den ausgewiesenen Wert,
    und die Zwischensummen addieren sich zur Gesamtsumme.

- **`BalanceReportExportTest`** wird erweitert
  - Vorschau, PDF und XLSX weisen denselben Gesamtwert aus.
  - Der Abschnitt trägt die Überschrift mit Stichtag und dem Zusatz
    `zeitraumunabhängig`.
  - Die Fußzeile mit der Zahl der unbepreisten Artikel erscheint, solange
    solche existieren, und entfällt, wenn alle Artikel einen Preis haben.

## Offene Punkte

Keine.
