# Bestandskonsistenz: Journal und Bestand wieder in Übereinstimmung bringen

Datum: 2026-09-28

## Ziel

`stocks.quantity` und das Bewegungsjournal `stock_movements` laufen in
Produktion auseinander. Zwei Defekte im Code verursachen das, ein dritter
Fehler verfälscht die Diagramme auf der Artikelseite. Diese Spezifikation
beschreibt die Behebung sowie eine einmalige Glattziehung der bereits
entstandenen Abweichung.

Der Anlass: Die Bestandsbilanz soll um einen Bestandswert je Artikel
erweitert werden. Solange beide Quellen sich widersprechen, steht diese
Auswertung auf keinem tragfähigen Fundament. Die Erweiterung folgt als
eigene Spezifikation, nachdem diese hier umgesetzt ist.

## Befund

Erhoben am 2026-09-28 auf dem Produktionssystem:

| Kennzahl | Wert |
| --- | --- |
| Artikel gesamt | 230 mit Bewegungen oder Bestand |
| Artikel mit Abweichung | 86 |
| davon Bestand **niedriger** als Journal | 82 (zusammen −52.870 Stück) |
| davon Bestand **höher** als Journal | 4 (zusammen +117 Stück) |
| Artikel mit gelöschter Lagerplatz-Zuordnung | 80 (156 Zuordnungen) |
| Buchungsgruppen mit identischem Zeitstempel | 40 über 29 Artikel |

Korrekturbuchungen erklären die Abweichung nicht: nur 29 der 86
betroffenen Artikel haben überhaupt welche.

### Ursache 1 — Löschen einer Zuordnung wird nicht journalisiert

`ArticleStorageLocationController::destroy()` löscht die `stocks`-Zeile und
schreibt keinen Bewegungseintrag. Die Zugänge bleiben im Journal stehen,
der Bestand verschwindet ersatzlos. Das erklärt die 82 Artikel mit zu
niedrigem Bestand und damit den weit überwiegenden Teil der Abweichung.

### Ursache 2 — verlorene Schreibvorgänge bei gleichzeitigen Buchungen

`StockMovementController::update()` liest den Bestand, rechnet und schreibt
zurück, ohne die Zeile zu sperren. Zwei Anfragen in derselben Sekunde lesen
denselben Ausgangswert und überschreiben sich gegenseitig. Das Journal
verbucht beide Klicks, der Bestand bewegt sich nur einmal.

Belegt an Karton 3 (Artikel-ID 36) am 2026-03-30: fünf `out 25` um
11:33:56, zwei weitere um 11:33:53, zwei `in 100` um 11:34:23. Die
Abweichung beträgt exakt +75, also drei Buchungen, die den Bestand nie
erreicht haben.

### Ursache 3 — fehlende Transaktionen

Keine der drei schreibenden Methoden in `ArticleStorageLocationController`
läuft in einer `DB::transaction()`, obwohl jede zwei Tabellen anfasst. Das
widerspricht der Regel in `CLAUDE.md` und kann bei einem Abbruch zwischen
den beiden Schreibvorgängen eine weitere Abweichung erzeugen.

### Nebenbefund — Diagramme zählen falsch

`ArticleManagementController::show()` wertet für die beiden Diagramme alle
Bewegungstypen aus. Dabei zählt es `transfer` als Zugang, obwohl ein
Transfer nur zwischen Lagerplätzen verschiebt und den Artikelbestand nicht
verändert. Heute folgenlos, weil es keine einzige Transferbuchung gibt.

Zudem zählt es `correction` mit. Nach der Glattziehung (siehe unten) würde
dort ein Ausschlag über mehrere tausend Stück erscheinen.

## Änderungen

### 1. `ArticleStorageLocationController` absichern

Alle drei schreibenden Methoden werden in eine `DB::transaction()` gefasst.

`destroy()` schreibt zusätzlich die fehlende Buchung. Liegt beim Entfernen
der Zuordnung noch Restbestand, wird er zuvor ausgebucht:

- `type` = `out`
- `from_storage_location_id` = der entfernte Lagerplatz
- `to_storage_location_id` = `null`
- `quantity` = der Restbestand
- `notes` = `Lagerplatz-Zuordnung entfernt`
- `user_id` = der angemeldete Benutzer

Erst danach wird die `stocks`-Zeile gelöscht. Beträgt der Restbestand 0,
entfällt die Buchung.

Die Entscheidung für `out` statt `correction` ist bewusst: die Ware ist,
soweit dieses System es wissen kann, aus dem Lager verschwunden. Die Bilanz
soll das als Abgang ausweisen, nicht als Buchhaltungskorrektur verstecken.

`correction()` schreibt künftig keine Bewegung mehr, wenn die Differenz 0
ist. Heute entsteht dabei eine Buchung mit Menge 0 und ohne Lagerplatz.

### 2. Bestandszeile beim Buchen sperren

In `StockMovementController::update()` wird die `stocks`-Zeile innerhalb
der bestehenden Transaktion gesperrt, bevor sie gelesen wird.

`Stock::firstOrCreate()` unterstützt kein `lockForUpdate()`. Der Ablauf
wird deshalb zweistufig: zuerst `firstOrCreate()` wie bisher, damit die
Zeile in jedem Fall existiert, danach dieselbe Zeile erneut mit
`lockForUpdate()` laden und ab da nur noch mit dieser Instanz arbeiten.

Das serialisiert gleichzeitige Buchungen auf denselben Artikel am selben
Lagerplatz. Buchungen auf andere Artikel oder Lagerplätze bleiben
unbeeinflusst, weil die Sperre nur diese eine Zeile betrifft.

### 3. Diagramme korrigieren

In `ArticleManagementController::show()` zählt die `switch`-Anweisung
künftig nur noch `in` und `out`:

- `in` erhöht, `out` verringert
- `transfer` verändert nichts, weil er den Artikelbestand nicht berührt
- `correction` verändert nichts, gleiche Regel wie in
  `BalanceReportService::build()`

Damit zeigen die Diagramme denselben Ausschnitt der Wirklichkeit wie die
Bilanz.

### 4. Einmalige Glattziehung

Ein Artisan-Kommando `lager:abgleichen` vergleicht je Artikel das Journal
mit dem Bestand und schreibt bei Differenz eine Korrekturbuchung.

**Die Bestände werden nicht verändert.** `stocks.quantity` ist die
zutreffende Größe, weil der Bestand tatsächlich entfernt wurde. Fehlerhaft
ist das Journal, dem der Eintrag darüber fehlt. Es wird also nur der
fehlende Eintrag nachgetragen.

Berechnung des Journalstands je Artikel:

```
  in                                          → + quantity
  out                                         → − quantity
  correction mit to_storage_location_id       → + quantity
  correction mit from_storage_location_id     → − quantity
  transfer                                    → 0
```

Differenz = `stocks`-Summe − Journalstand. Ist sie ungleich 0, entsteht
eine Buchung:

- `type` = `correction`
- Richtung: bei negativer Differenz `from_storage_location_id`, bei
  positiver `to_storage_location_id`
- `quantity` = Betrag der Differenz
- Lagerplatz: aus der jüngsten Bewegung dieses Artikels
  `to_storage_location_id`, ersatzweise `from_storage_location_id`;
  hat der Artikel gar keine Bewegung, die Lagerplatz-ID seines ersten
  `stocks`-Eintrags. Existiert beides nicht, wird der Artikel
  übersprungen und im Bericht als nicht abgleichbar ausgewiesen.
- `user_id` = 1 (Administrator). Bewusst nicht der Benutzer, der die 3.848
  echten Buchungen getätigt hat — dies ist ein Systemvorgang.
- `notes` = `Abgleich Journal und Bestand, Ursache: gelöschte
  Lagerplatz-Zuordnungen ohne Buchung`

Optionen des Kommandos:

- ohne Argumente: Probelauf. Zeigt je betroffenem Artikel Bestand,
  Journalstand und Differenz sowie die Gesamtzahl, schreibt nichts.
- `--schreiben`: führt die Buchungen aus, in einer Transaktion je Artikel.

Der Probelauf ist der Standard, damit ein versehentlicher Aufruf nichts
verändert.

**Erwartet und kein Fehler:** Nach der Glattziehung stimmt der Bestand mit
dem vollständigen Journal überein, nicht aber mit der Summe aus `in` und
`out` allein. Die Bilanz zählt Korrekturen bewusst nicht mit und ist eine
Bewegungsrechnung, kein Bestandsausweis. Wer beide Zahlen später vergleicht,
darf daraus nicht auf einen neuen Defekt schließen.

### 5. Ablauf in Produktion

1. Vollständiges `mysqldump`-Backup der Datenbank in eine Datei mit
   Zeitstempel, Größe und Zeilenzahl werden geprüft und dem Nutzer
   gezeigt.
2. Probelauf `lager:abgleichen`, Bericht wird gesichtet.
3. Erst danach `lager:abgleichen --schreiben`.
4. Nachkontrolle: erneuter Probelauf muss 0 betroffene Artikel melden.

Alle `artisan`-Aufrufe auf dem Server laufen über
`/opt/plesk/php/8.5/bin/php`, weil das Standard-`php` dort 8.3.6 ist und
`composer.json` seit dem Lieferanten-Feature PHP 8.4 verlangt.

## Nicht Teil dieser Spezifikation

- Die Erweiterung der Bilanz um den Bestandswert. Sie folgt als eigene
  Spezifikation, sobald diese hier umgesetzt ist.
- Die beiden bekannten Routendefekte in `routes/web.php`
  (`/articles/trashed` hinter `/articles/{article}`, Tippfehler bei
  `storage-locations.get`).
- Deutsche Sprachdateien für Validierungsmeldungen.
- Die Rekonstruktion der Historie: wann welche Zuordnung mit welchem
  Bestand entfernt wurde, steht nirgends und lässt sich nicht
  nachträglich ermitteln. Die Glattziehung datiert deshalb auf den Tag
  ihrer Ausführung.

## Tests

Alle Tests mit `RefreshDatabase` gegen die MySQL-Datenbank `goods_test`.

- **`ArticleStorageLocationTransaktionTest`**
  - Entfernen einer Zuordnung mit Restbestand schreibt eine `out`-Buchung
    über genau diesen Restbestand, mit korrektem Lagerplatz und Notiz.
  - Entfernen einer Zuordnung ohne Restbestand schreibt keine Buchung.
  - Nach dem Entfernen stimmen Journalstand und Bestand des Artikels
    überein.
  - `correction()` mit unveränderter Menge schreibt keine Buchung.
  - Schlägt das Schreiben der Bewegung fehl, bleibt auch der Bestand
    unverändert. Erzwungen über einen `DB::beforeExecuting`-Hook, der beim
    Insert in `stock_movements` eine Ausnahme wirft; danach muss die
    `stocks`-Zeile unverändert vorhanden sein.

- **`StockMovementSperreTest`**
  - Die Bestandszeile wird innerhalb der Transaktion mit `lockForUpdate()`
    geladen. Prüfung über `DB::listen`, dass die Abfrage ein `for update`
    enthält.
  - Eine gewöhnliche Buchung funktioniert unverändert (Rückfallprüfung).

- **`ArtikelDiagrammTest`**
  - Eine `correction` verändert die Diagrammwerte nicht.
  - Eine `transfer` verändert die Diagrammwerte nicht.
  - `in` und `out` werden weiterhin korrekt verrechnet.

- **`LagerAbgleichKommandoTest`**
  - Probelauf meldet die Differenz und schreibt nichts.
  - `--schreiben` erzeugt je betroffenem Artikel genau eine
    Korrekturbuchung mit richtiger Richtung und Menge.
  - Die Bestände bleiben dabei unverändert.
  - Ein zweiter Lauf meldet 0 betroffene Artikel.
  - Artikel ohne Abweichung erzeugen keine Buchung.

## Offene Punkte

Keine.
