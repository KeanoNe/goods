# Lieferanten und Bilanz-Export

Datum: 2026-09-20

## Ziel

Die Lagerverwaltung bekommt Lieferanten als eigene Stammdaten. Jeder Artikel
kann mehreren Lieferanten zugeordnet werden, jede Zuordnung trägt einen eigenen
Stückpreis. Beim Ein- und Ausbuchen wird der Lieferant miterfasst, damit sich
für einen frei wählbaren Zeitraum eine Bestandsbilanz als PDF oder Excel
erzeugen lässt.

## Datenmodell

Alles Neue liegt in einer eigenen Migration. Die bestehende Schema-Migration
`2024_12_08_145642_create_datastructure.php` bleibt unangetastet.

### Tabelle `suppliers`

| Spalte | Typ | Anmerkung |
| --- | --- | --- |
| `id` | bigint | |
| `name` | string | Pflicht |
| `contact_person` | string | nullable |
| `email` | string | nullable |
| `phone` | string | nullable |
| `address` | text | nullable |
| `customer_number` | string | nullable, unsere Kundennummer beim Lieferanten |
| `notes` | text | nullable |
| `timestamps` | | |
| `softDeletes` | | wie bei allen anderen Stammdaten |

### Tabelle `article_supplier`

| Spalte | Typ | Anmerkung |
| --- | --- | --- |
| `id` | bigint | |
| `article_id` | FK articles | `onDelete('cascade')` |
| `supplier_id` | FK suppliers | `onDelete('cascade')` |
| `price` | decimal(10,2) | Stückpreis dieses Lieferanten für diesen Artikel |
| `is_default` | boolean, default false | Vorauswahl beim Buchen |
| `timestamps` | | |

Unique auf `(article_id, supplier_id)`.

**Genau ein Standard je Artikel** lässt sich in MySQL nicht per Unique-Index
erzwingen, weil es keine Partial Indexes gibt. Die Invariante wird im
Controller in einer `DB::transaction()` hergestellt: erst alle Pivot-Zeilen des
Artikels auf `is_default = false`, dann die gewählte auf `true`.

### Erweiterung `stock_movements`

| Spalte | Typ | Anmerkung |
| --- | --- | --- |
| `supplier_id` | FK suppliers, nullable | ohne `onDelete`-Regel, vgl. `forceDelete` unter „Lieferantenverwaltung" |
| `unit_price` | decimal(10,2), nullable | Preis-Snapshot zum Buchungszeitpunkt |

`unit_price` wird beim Buchen aus `article_supplier.price` kopiert. Eine
spätere Preisänderung verändert bereits gebuchte Bewegungen nicht — eine einmal
erzeugte Bilanz bleibt reproduzierbar.

**`unit_price` liest ausschließlich der Server aus der Pivot-Tabelle.** Der
Wert wird nie aus dem Request übernommen, sonst könnte der Client den
Bilanzwert frei bestimmen.

### Models

- `Supplier` — `SoftDeletes`, `HasFactory`; `belongsToMany(Article::class)`
  mit `withPivot('price', 'is_default')`, `hasMany(StockMovement::class)`.
- `Article` — zusätzlich `belongsToMany(Supplier::class)` mit denselben
  Pivot-Feldern.
- `StockMovement` — zusätzlich `belongsTo(Supplier::class)`.

## Lieferantenverwaltung

`SupplierManagementController` folgt exakt dem Muster der bestehenden
Management-Controller: `index`, `create`, `store`, `edit`, `update`, `destroy`,
`trashed`, `restore`, `forceDelete`. Inline-Validierung per
`$request->validate([...])`, Antworten per
`redirect()->route(...)->with('message', '<deutscher Text>')`.

Seiten: `Pages/Suppliers/Index.vue`, `Pages/Suppliers/UpsertSupplier.vue`
(Upsert-Pattern, Edit-Modus am Model-Prop erkannt),
`Pages/Suppliers/Trashed.vue`.

Routen in der üblichen Middleware-Kette. Die statische Route
`/suppliers/trashed` steht **vor** `/suppliers/{supplier}` — der in `CLAUDE.md`
dokumentierte Defekt bei `/articles/trashed` wird nicht mitkopiert.

`forceDelete` wird abgelehnt, solange Bewegungen auf den Lieferanten zeigen.
Das Bewegungsjournal ist unveränderlich und soll seine Historie behalten. Der
Controller prüft das und gibt eine deutsche Fehlermeldung zurück, statt einen
SQL-Constraint-Fehler durchschlagen zu lassen.

## Preispflege am Artikel

`Pages/Articles/Show.vue` bekommt den Abschnitt „Lieferanten & Preise":

- Tabelle mit Lieferant, Stückpreis, Standard (Radio), Entfernen.
- Darunter eine Zeile zum Zuordnen eines weiteren Lieferanten mit Preis.

Bedient von `ArticleSupplierController`, analog zum vorhandenen
`ArticleStorageLocationController`:

- `POST   /articles/{article}/suppliers` — zuordnen (`supplier_id`, `price`, `is_default`)
- `PUT    /articles/{article}/suppliers/{supplier}` — Preis oder Standard ändern
- `DELETE /articles/{article}/suppliers/{supplier}` — Zuordnung lösen

## Buchen mit Lieferant

`StockMovementController::getLocation()` liefert pro Artikel zusätzlich:

- `suppliers` — Liste aus `id`, `name`, `price`
- `default_supplier_id` — der als Standard markierte Lieferant, sonst `null`

`StockMovementController::update()`:

- validiert `supplier_id` als `nullable` gegen die **Pivot-Tabelle**, nicht
  gegen `suppliers`. Ein Lieferant, der dem Artikel nicht zugeordnet ist, wird
  abgelehnt.
- liest `unit_price` aus `article_supplier` und schreibt es zusammen mit
  `supplier_id` auf die Bewegung — innerhalb der bestehenden `DB::transaction()`.

Frontend `Pages/StockMovement/Index.vue`: ein `<select>` „Lieferant" unter der
Mengeneingabe, vorbelegt mit `default_supplier_id`. Die Option
„— kein Lieferant —" bleibt wählbar; Buchungen ohne Lieferant sind erlaubt und
erscheinen im Export als Zeile „Ohne Lieferant". Gilt für Ein- und Ausbuchen
gleichermaßen.

**Bewusst ausgeklammert:** Korrekturbuchungen
(`ArticleStorageLocationController::correction`, Typ `correction`) bleiben ohne
Lieferant. Sie sind Inventurdifferenzen, kein Warenfluss.

## Bilanz-Export

### Seite

`Pages/Reports/Balance.vue` mit Von-/Bis-Datum, der Bilanz als Tabellenvorschau
und zwei Buttons „PDF" und „Excel". Die Vorschau kostet kaum Zusatzaufwand,
weil alle drei Ausgaben dieselben aggregierten Daten nutzen.

Vorbelegt ist der laufende Monat (erster Tag des Monats bis heute), damit die
Seite ohne Eingabe schon etwas Sinnvolles zeigt. `to` muss auf oder nach `from`
liegen, sonst Validierungsfehler.

Routen:

```php
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/balance', [BalanceReportController::class, 'index'])->name('balance.index');
    Route::get('/balance/export', [BalanceReportController::class, 'export'])->name('balance.export');
});
```

`export` nimmt `from`, `to` und `format` (`pdf` oder `xlsx`).

### Aufbau des Dokuments

Kopfbereich: Titel „Bestandsbilanz", ausgewerteter Zeitraum
(„01.01.2026 – 31.03.2026") und Erstellungsdatum.

Tabelle:

| Artikelnummer | Bezeichnung | Lieferant | Menge | Stückpreis | Gesamtwert |
| --- | --- | --- | --- | --- | --- |

- **Menge** = Eingänge − Ausgänge im Zeitraum (Netto).
- **Gesamtwert** = Menge × Stückpreis.
- Je Artikel eine Zwischensumme.
- Ganz unten die Gesamtsumme über alle Artikel.

Darunter der Unterschriftenblock, zwei leere Linien zum handschriftlichen
Ausfüllen:

```
_______________________          _______________________
Ort, Datum                       Unterschrift
```

Beide Felder bleiben leer. Das Datum wird bewusst nicht vorbefüllt, sonst steht
auf einem später unterschriebenen Dokument ein falsches Datum. Im XLSX kommt
derselbe Block als Zeilen unter die Tabelle, damit ein Ausdruck aus Excel
identisch aussieht.

### Aggregation

`App\Services\BalanceReportService::build($from, $to)` macht **eine**
Aggregat-Query über `stock_movements`:

```sql
select article_id, supplier_id, unit_price,
       sum(case when type = 'in'  then quantity else 0 end) as qty_in,
       sum(case when type = 'out' then quantity else 0 end) as qty_out
from stock_movements
where created_at between :from and :to
  and type in ('in', 'out')
group by article_id, supplier_id, unit_price
```

Danach Gruppierung in PHP zu Artikelblöcken mit Zwischensumme und einer
Gesamtsumme.

**Gruppiert wird nach Artikel + Lieferant + Stückpreis**, nicht nur nach
Artikel + Lieferant. Hat sich der Preis eines Lieferanten mitten im Zeitraum
geändert, existieren für dieselbe Kombination zwei verschiedene
Snapshot-Preise. Ohne die Preisgruppierung müsste in der Spalte „Stückpreis"
ein gewichteter Durchschnitt stehen — dann stimmt `Menge × Stückpreis` nicht
mehr exakt mit dem Gesamtwert überein — oder einer der Preise willkürlich
gewählt werden. Mit der Preisgruppierung erscheint der Artikel in so einem Fall
in zwei Zeilen (etwa 100 Stück à 1,00 € und 50 Stück à 1,50 €), und jede Zeile
ist von Hand nachrechenbar. Bei einem unterschriebenen Dokument ist das die
richtige Eigenschaft. Solange sich kein Preis geändert hat, ändert sich an der
Darstellung nichts.

Weitere Festlegungen:

- Nur `type in ('in', 'out')`. `transfer` verschiebt nur intern und ändert den
  Gesamtwert nicht, `correction` hat keinen Preis.
- Bewegungen ohne Lieferant erscheinen als Zeile „Ohne Lieferant".
- Artikel- und Lieferantennamen werden mit `withTrashed()` geladen, sonst
  fallen soft-gelöschte Stammdaten aus der Bilanz heraus.
- Zeitraum inklusiv: `whereBetween` auf `startOfDay` / `endOfDay`.
- Die Netto-Menge kann negativ sein (mehr aus- als eingebucht). Das wird
  unverändert ausgewiesen, nicht auf null geklemmt.

### Ausgabe

- **PDF** — Blade-View `resources/views/reports/balance.blade.php`, A4
  hochkant, gerendert über `barryvdh/laravel-dompdf`.
- **XLSX** — streamend geschrieben mit `openspout/openspout`, Kopfzeile fett,
  gleiche Spalten wie das PDF.
- Dateiname jeweils `bilanz_2026-01-01_bis_2026-03-31.pdf` bzw. `.xlsx`.

### Neue Abhängigkeiten

`barryvdh/laravel-dompdf` und `openspout/openspout`. Vom Nutzer freigegeben.

## Navigation

`resources/js/Layouts/AppLayout.vue` bekommt die Einträge „Lieferanten" und
„Bilanz", jeweils im Desktop- und im Mobile-Menü.

## Tests

Alle Tests mit `RefreshDatabase`, wie im Projekt üblich.

Zuerst die laut `CLAUDE.md` fehlenden Factories anlegen: `Article`, `Stock`,
`StockMovement` und neu `Supplier`.

- **`SupplierManagementTest`** — CRUD, Soft-Delete, Papierkorb, Restore;
  `forceDelete` wird abgelehnt, solange Bewegungen auf den Lieferanten zeigen.
- **`ArticleSupplierPriceTest`** — Zuordnen, Preis ändern, Zuordnung lösen;
  nach dem Setzen eines neuen Standards hat der Artikel genau eine Pivot-Zeile
  mit `is_default = true`.
- **`StockMovementSupplierTest`** — Buchen mit Lieferant schreibt `supplier_id`
  und `unit_price`; eine spätere Preisänderung lässt die Bewegung unverändert;
  Buchung ohne Lieferant ist erlaubt; ein dem Artikel nicht zugeordneter
  Lieferant wird abgelehnt.
- **`BalanceReportTest`** — Netto-Berechnung, Zwischensummen je Artikel,
  Gesamtsumme; Zeitraumgrenzen (Bewegung am ersten und letzten Tag ist drin,
  einen Tag davor beziehungsweise danach nicht); Zeile „Ohne Lieferant";
  `transfer` und `correction` bleiben draußen; Preisänderung im Zeitraum
  erzeugt zwei Zeilen; beide Exporte liefern 200 mit korrektem Content-Type;
  das PDF enthält den Unterschriftenblock.
- **`InertiaSeitenSmokeTest`** wird um die neuen Seiten erweitert.

## Offene Punkte

Keine.
