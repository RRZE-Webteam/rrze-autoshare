# RRZE Autoshare: Anleitung für Redaktion und Administration

RRZE Autoshare veröffentlicht Hinweise auf neue Beiträge automatisch bei konfigurierten externen Diensten. Derzeit stehen Bluesky, Mastodon und Matrix zur Verfügung.

## Für lokale Administratoren

### Plugin aktivieren

Das Plugin ist im CMS bereits vorhanden und muss nicht installiert werden. Aktivieren Sie es bei Bedarf unter **Plugins**. Anschließend finden Sie die Konfiguration unter **Einstellungen -> RRZE-Autoshare**.

### Dienst einrichten

Für jeden Dienst sind zunächst die Zugangsdaten zu hinterlegen und zu autorisieren. Ein Dienst kann erst unter **Allgemeines** aktiviert werden, wenn die Autorisierung erfolgreich war.

- **Bluesky:** Verwenden Sie die Konto-Kennung oder E-Mail-Adresse sowie ein Bluesky-App-Passwort. Verwenden Sie nicht das normale Kontopasswort.
- **Mastodon:** Hinterlegen Sie die URL der Mastodon-Instanz und einen Zugriffstoken der dafür angelegten Anwendung. Die Anwendung benötigt die Rechte `read:accounts`, `write:statuses` und bei Beitragsbildern `write:media`.
- **Matrix:** Hinterlegen Sie die HTTPS-URL des Homeservers, mindestens eine Raum-ID und einen Bearer-Token. Der Matrix-Account muss Mitglied im Zielraum sein und dort Nachrichten senden dürfen.

Die Formate der Nachrichten lassen sich pro Dienst anpassen. Die darunter aufgeführten Platzhalter zeigen, welche Inhalte für den jeweiligen Dienst verfügbar sind. Zugangsdaten und Tokens gehören nur in die dafür vorgesehenen Felder und dürfen nicht weitergegeben werden.

### Senderegeln festlegen

Unter **Senderegeln** wählen Sie zwischen zwei Betriebsarten:

- **Standardregeln:** Jeder neu veröffentlichte Beitrag wird an alle aktiven Dienste gesendet. Matrix sendet dabei an alle in den Matrix-Einstellungen eingetragenen Räume.
- **Erweiterte Regeln:** Pro Dienst legen Sie fest, bei welchem Beitragsstatus sowie welchen Kategorien oder Schlagwörtern gesendet wird. Innerhalb einer Kategorien- oder Schlagwortauswahl genügt jeweils ein zutreffender Wert. Kategorien und Schlagwörter werden hingegen gemeinsam berücksichtigt.

Für Matrix können mehrere Regeln angelegt werden. Jede Regel kann einen oder mehrere der hinterlegten Zielräume wählen. Treffen mehrere Matrix-Regeln zu, erhält jeder ausgewählte Raum genau eine Nachricht.

### Manuell senden und Fehler prüfen

Im Tab **Manuell senden** können Sie nach einem Beitrag suchen und ihn gemäß den aktuell gespeicherten Regeln erneut senden. Aktive und autorisierte Dienste sind vorausgewählt. Das Ergebnis zeigt für jeden Dienst an, ob der Versand erfolgreich war, übersprungen wurde oder fehlgeschlagen ist.

Bei Problemen prüfen Sie zuerst die Autorisierung und die Senderegeln. Für weitergehende technische Prüfung kann unter **Allgemeines -> Debugging** die Ausgabe informativer Meldungen in den RRZE-Log aktiviert werden. Diese Option ist nur für berechtigte Administratoren sichtbar.

## Für Redakteure

Beim Bearbeiten eines Beitrags finden Sie in der rechten Seitenleiste des Block Editors den Bereich **Autoshare**.

- Ist **Autoshare aktiviert**, wird der Beitrag beim Erreichen eines passenden Status nach den Regeln der Website gesendet.
- Ist der Schalter deaktiviert, wird dieser einzelne Beitrag nicht automatisch geteilt.

Welche Dienste und Zielräume verwendet werden, entscheiden die lokalen Administratoren über die Senderegeln. Redakteure müssen daher keine Zugangsdaten verwalten und können im Beitrag keine einzelnen Dienste auswählen.

Prüfen Sie vor der Veröffentlichung besonders Titel, Auszug, Beitragsbild und Kategorien beziehungsweise Schlagwörter. Diese Angaben bestimmen Inhalt und Empfängerkreis der Nachricht.

## Hinweise

- Ein bereits veröffentlichter Beitrag wird nicht durch bloßes Aktualisieren erneut versendet. Nutzen Sie dafür den Tab **Manuell senden**.
- Dienste mit abgelaufener oder ungültiger Autorisierung werden automatisch deaktiviert und müssen erneut autorisiert werden.
- Bei vorübergehenden Rate Limits oder Serverfehlern versucht das Plugin den Versand erneut. Nach mehreren erfolglosen Versuchen wird der Vorgang im Log vermerkt.
