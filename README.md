[![Aktuelle Version](https://img.shields.io/github/package-json/v/rrze-webteam/rrze-autoshare/main?label=Version)](https://github.com/RRZE-Webteam/rrze-autoshare) [![Release Version](https://img.shields.io/github/v/release/rrze-webteam/rrze-autoshare?label=Release+Version)](https://github.com/rrze-webteam/rrze-autoshare/releases/) [![GitHub License](https://img.shields.io/github/license/rrze-webteam/rrze-autoshare)](https://github.com/RRZE-Webteam/rrze-autoshare) [![GitHub issues](https://img.shields.io/github/issues/RRZE-Webteam/rrze-autoshare)](https://github.com/RRZE-Webteam/rrze-autoshare/issues)

# RRZE Autoshare

Dieses Plugin sendet neu veröffentlichte WordPress-Beiträge gemäß konfigurierter Regeln an externe Dienste wie Bluesky, Mastodon und Matrix.

## Contributors

* RRZE-Webteam, https://www.rrze.fau.de

## Copyright

GNU General Public License (GPL) Version 3

## Dokumentation

Die oeffentliche Dokumentation und Endanwender-Hinweise liegen unter:

* https://www.wp.rrze.fau.de

## Feedback

* Issues und Feedback: https://github.com/RRZE-Webteam/rrze-autoshare/issues
* Kontakt: webmaster@rrze.fau.de



## Administration

Die Einstellungen befinden sich unter **Einstellungen -> RRZE-Autoshare**. Zuerst werden die Zugangsdaten eines Dienstes autorisiert und anschließend der Dienst unter **Allgemeines** aktiviert. Nicht autorisierte Dienste können nicht aktiviert werden.

Unter **Senderegeln** gilt entweder die Standardregel (alle neu publizierten Beiträge an alle aktiven Dienste) oder es werden dienstspezifische Regeln für Beitragsstatus, Kategorien und Schlagwörter festgelegt. Für Matrix können mehrere Regeln mit unterschiedlichen Zielräumen angelegt werden. Treffen mehrere Matrix-Regeln zu, wird jeder ausgewählte Raum einmal beliefert.

Im Block Editor schaltet **Autoshare aktiviert** die automatische Übertragung für den einzelnen Beitrag ein oder aus. Der Tab **Manuell senden** ermöglicht es Administratoren, einen vorhandenen Beitrag gemäß den aktuell gültigen Regeln erneut zu senden.

Die ausführliche Anleitung für Redaktionen und Anwender steht unter [wp.rrze.fau.de](https://www.wp.rrze.fau.de).

## Entwicklung

`package.json` ist die verbindliche Quelle für Version und Kompatibilitätsdaten. Änderungen an PHP, JavaScript, SCSS oder Übersetzungen werden nicht in `build/` gepflegt. Für die Entwicklung wird `npm run dev` verwendet; `npm run prod` erzeugt minifizierte Produktionsdateien ohne Source-Maps.

Das Plugin verwendet einen lokalen PSR-4-Autoloader. Externe Dienste liegen unter `includes/Services/`. Filter für Titel, Auszug und Schlagwörter sind je Dienst in `includes/Config.php` definiert, derzeit für Bluesky, Mastodon und Matrix. Fehler und Warnungen werden über die Actions `rrze.log.error` und `rrze.log.warning` protokolliert; Info-Meldungen über `rrze.log.info` sind in den Einstellungen optional aktivierbar.
