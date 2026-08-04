# ViMi Pad — Beta-Testing

Dieses Dokument beschreibt, wie Beta-Tests bei mod_vimipad ablaufen: wie Tester
Rückmeldungen geben, wie Reklamationen priorisiert und abgesichert werden, und
wie eine Testinstanz mit Beispieldaten schnell aufgesetzt wird.

## Für Beta-Tester: Rückmeldung geben

1. Öffne ein Issue im GitHub-Repository und wähle die Vorlage **Bug report**
   (Fehler) oder **Feature / change request** (Änderungswunsch).
2. Beschreibe **was du erwartet hast** und **was tatsächlich passiert ist**.
3. Gib an: Moodle-Version, Browser, Rolle (Lehrende:r/Lernende:r), und ob es
   sich um Einzel-, Gruppen- oder Kursmodus handelt.
4. Wenn möglich: Schritte zum Reproduzieren, ein Screenshot und die
   Aktivitäts-URL.

Es gibt keine dummen Rückmeldungen. Auch „das war verwirrend" ist wertvoll.

## Triage: Prioritäten

Jedes Issue erhält eine Priorität. Maßgeblich ist die Auswirkung auf Lernende
und auf die Datenintegrität, nicht der Aufwand.

| Prio | Bedeutung | Beispiel | Reaktion |
|------|-----------|----------|----------|
| P0 | Datenverlust, Sicherheitsleck, Aktivität unbenutzbar | Bewertete Snapshots verschwinden | sofort, blockiert Release |
| P1 | Kernfunktion defekt, kein Workaround | Sperren greifen nicht | vor dem nächsten Release |
| P2 | Funktion eingeschränkt, Workaround vorhanden | Anordnen sieht unschön aus | eingeplant |
| P3 | Kosmetik, Kleinigkeit | Tooltip-Tippfehler | gesammelt |

## Regel: Jede Reklamation wird zum Test

Für **jede** bestätigte Reklamation gilt: bevor sie als erledigt gilt, wird sie
durch einen automatisierten Test abgesichert (PHPUnit für Server-/Datenlogik,
Jest für Frontend-Verhalten, Behat für durchgängige Abläufe). So kann derselbe
Fehler nicht unbemerkt zurückkehren. Der Fix-Commit nennt die Reklamation und
den zugehörigen Test.

## Testinstanz mit Beispieldaten

Die vollständige Anleitung zum Aufsetzen einer Moodle-4.5-Testumgebung steht in
`docs/dev/moodle-test-environment-setup.md`.

Für Beispieldaten stehen zwei Wege bereit:

- **Behat/CI** — der Datengenerator kann eine dimensionierte Map in einem
  Schritt seeden:

  ```gherkin
  Given the following "mod_vimipad > maps" exist:
    | vimipad   | user     | size   |
    | VimiPad 1 | student1 | small  |
  ```

  Größen: `small` (20 Nodes / 30 Relationen / 5 Container), `medium`
  (200 / 400 / 40), `large` (1000 / 2000 / 200). Das sind Test-Profile, keine
  Produktgrenzen.

- **PHPUnit/Programmierung** — derselbe Generator bietet granulare Methoden
  (`create_node`, `create_relation`, `create_container`, `create_membership`,
  `create_operations`, `create_snapshot`, `create_grade`, `create_peer_review`)
  und die Lastprofile `create_map_profile()` bzw. `create_collaboration_history()`.

## Betriebsdiagnose (Check API)

Unter **Website-Administration → Berichte → Sicherheitschecks/Statuschecks**
meldet mod_vimipad drei Statuschecks: Datenintegrität (verwaiste Zeilen),
Subplugin-Registrierung und Verlaufsgröße. Ein Blick dorthin zeigt schnell, ob
eine Instanz gesund ist.
