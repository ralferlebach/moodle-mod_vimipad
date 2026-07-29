# Bewertungs-Architektur (mod_vimipad)

Bewertung der in der Literatur etablierten automatischen Verfahren (Vorlage:
Übersichtstext des Maintainers, u. a. Kit-Build/FMS/SMS, NLP/LLM, Topologie,
referenzfreie Verfahren, Fuzzy, OpenIE, Peer-Matrix, sowie formspezifische
Verfahren für Mindmaps, Argument Maps, Causal Loops, Wissensgraphen) und deren
Einordnung in die Arbeitsplanung. Rahmenbedingungen: Moodle/PHP (kein
Python/NetworkX), LLM- und Embedding-Zugriff ausschließlich über Moodles
AI-Subsystem, Musterlösungen via vorhandenem Import.

## Grundsatzentscheidung: Hybrid

- **Fix im Kern:** manueller Bewertungs-Workflow (Snapshot → Note/Feedback →
  Gradebook → Abschluss), Annotationen, KI-Feedback-Entwurf, Struktur-Metriken
  als Hilfsanzeige, sowie die Einbindung von Moodles Kern-`gradingform`
  (Rubrik/Bewertungsleitfaden) — kein Eigenbau von Rubriken.
- **Subplugin-Typ `vimipadassess`:** ausschließlich die automatischen
  Bewertungs-Strategien ("Scorer"). Hier existiert echte fachliche Vielfalt;
  Forschung soll eigene Metriken ergänzen können, ohne den Kern anzufassen.
- **Zwei Subplugin-Achsen:** `vimipadform` definiert die Darstellungsform
  (Concept Map, Baum/Mindmap, künftig Argument Map, Causal Loop, …);
  `vimipadassess` definiert die Bewertung. Ein Scorer deklariert, welche
  Profile er unterstützt (z. B. Tree-Edit-Distance nur für Baum-Profile).

## Der Scorer-Vertrag (Grundlage, wird sofort gebaut)

Eingabe: eingereichter Snapshot, 0..n Musterlösungen, Aktivitäts-/
Scorer-Konfiguration, Kontext. Ausgabe: **Vorschlag, nie Festsetzung** —
Punktzahl-Vorschlag plus nachvollziehbare Aufschlüsselung:

- Treffer-Listen (Begriffe/Propositionen: getroffen, fehlend, überzählig),
- **jeder Treffer mit Gewicht 0.0–1.0** (Fuzzy-fähig von Anfang an; exaktes
  Matching liefert schlicht 1.0),
- Teil-Scores je Dimension (Inhalt, Struktur, …), maschinenlesbar + anzeigbar.

Innerhalb inhaltlicher Scorer ist der **Matcher austauschbar** (Schnittstelle):
exakt/normalisiert (Lowercase, Trim, Lemma-Näherung) sofort; Levenshtein
kurzfristig; Embedding-/Ontologie-Matcher später über dieselbe Schnittstelle.
Geteilte Kern-Dienste für alle Scorer: Propositions-Extraktion
(Snapshot → Tripel Quelle–Relation–Ziel) und **Tuple-to-Text**
(Propositionsliste → Fließtext, Grundlage für LLM-Prompts und OpenIE-Weg).

## Bewertung der Verfahren aus der Übersicht

| Verfahren (Übersicht) | Machbarkeit | Einordnung |
|---|---|---|
| Graph-Metriken (Dichte, Zentralität, Tiefe, Cross-Links, UFS-Features) | Hoch, reines PHP | **Fix, jetzt**: Hilfsanzeige im Bewertungs-Reiter; später auch als referenzfreier Scorer. Nur als Ergänzung valide (Übersicht: „strukturell belohnt Unsinn") — daher nie Auto-Note allein. |
| Full Map Scoring (FMS) | Mittel; volle Subgraph-Isomorphie NP-schwer → propositionaler Abgleich | **Subplugin `reference` (erste Ausbaustufe)**: Begriffs- + Propositions-Abgleich (Precision/Recall/F1), Pfeilrichtung als eigener Prüfpunkt (Übersicht: häufige Fehlerquelle). |
| Sub-Map Scoring (SMS) | Mittel | **v2 des `reference`-Scorers**, aufsetzend auf `vimipad_container` (Cluster existieren im Datenmodell). |
| Kit-Build (geschlossenes Format) | Mittel; primär Editor-/Aufgabenmodus | **v2-Feature (fix)**: Aktivitätsoption „nur vorgegebene Begriffe/Phrasen" (Vorgabe aus importierter Musterlösung); Bewertung dann exaktes FMS. |
| NLP-Normalisierung + Word Embeddings | Normalisierung: jetzt; Embeddings: extern | Matcher-Schnittstelle **jetzt**; Embedding-Matcher **v2** über AI-Subsystem/extern. |
| LLM-Scoring (mehrstufiges Schema) | Mittel; AI-Subsystem existiert (`ai_feedback_service`) | **Subplugin `llm` (zweite Ausbaustufe)**: nutzt Tuple-to-Text + Rubrik-Kriterien im Prompt; Kosten/Validität → immer nur Vorschlag. |
| Referenzfrei (Kohärenz, Hierarchie-/Asymmetrie-Indizes) | Indizes: jetzt (PHP); Kohärenz: braucht Semantik | **Subplugin `structure`** (referenzfrei, Index-Teil) nach dem `reference`-Scorer; semantische Kohärenz → LLM-Weg. |
| Fuzzy-Gewichte / Assoziationsregeln | Konzept jetzt, Daten später | **Grundlage jetzt** (0..1-Gewichte im Vertrag); korpusbasierte Gewichte v2+, wenn historische Daten vorliegen. |
| OpenIE / Tuple-to-Text + ROUGE/LSA | Tuple-to-Text: trivial; ROUGE (n-Gramm): machbar; LSA: nein | Tuple-to-Text **jetzt** als Kern-Dienst; ROUGE-Scorer optional v2 (geringe Priorität — direkter Propositions-Abgleich ist aussagekräftiger). |
| Peer-Matrix / Multi-Cmap | Vereinfachte Variante machbar (Kanten-Häufigkeit der Kohorte); PCA unnötig schwer | **Subplugin `peer` v2**; braucht große Kohorten, sonst invalide. |
| Mindmap: Tree Edit Distance + Pfad-Semantik | TED (Zhang-Shasha) in PHP machbar für kleine Bäume | **Subplugin v2**, deklariert Profil `tree`; Pfad-Semantik zunächst per String-Matcher, semantisch via LLM/Embeddings später. |
| Argument Map: DAG-Polarität + Argument Mining | Polaritäts-Logik: PHP machbar; Mining: LLM | Setzt **künftiges `vimipadform`-Profil „Argument Map"** voraus (Kanten-Polarität im Datenmodell via metadatajson). Scorer dann v2/v3. |
| Causal Loop: Zyklen (Tarjan), Loop-Polarität | PHP machbar | Setzt künftiges Profil „Causal Loop" voraus; Scorer v2/v3. Zyklenerkennung ggf. früher als Metrik. |
| Wissensgraph: DL-Reasoner, GNNs | In Moodle-PHP nicht leistbar | **Extern / nicht geplant**; allenfalls Anbindung eines externen Dienstes über ein Drittanbieter-Subplugin. |

## Ausbaustufen (Arbeitsplanung „Bewertung")

1. **Bewertungs-Reiter (fix):** Inhalte aus `grade.php` in den Reiter überführen
   (Snapshot-Ansicht, Note/Feedback, Annotationen, KI-Entwurf), Ansichts-
   Selektor, Struktur-Metriken als Hilfsanzeige je Einreichung.
2. **Kern-`gradingform`:** Rubrik/Bewertungsleitfaden über Moodles Advanced
   Grading (Bereich `mod_vimipad`, Item `submissions`).
3. **`vimipadassess`-Typ + Vertrag:** Registry analog `vimipadform`; geteilte
   Dienste Propositions-Extraktion + Tuple-to-Text; Aktivitätseinstellung,
   welche Scorer aktiv sind; Anzeige der Vorschläge im Bewertungs-Reiter
   (Vorschlag → Lehrkraft übernimmt/ändert). Musterlösungs-Ablage: importierte
   Referenz je Aktivität als solche markieren.
4. **Scorer `reference`:** Begriffs- + Propositions-Abgleich (Matcher: exakt/
   normalisiert, Levenshtein optional), Richtungsprüfung, F1-Aufschlüsselung.
5. **Weitere Scorer (v2, je als Subplugin):** `structure` (referenzfrei),
   `llm` (AI-Subsystem), `peer` (Kohorten-Häufigkeit), `tree` (TED), SMS-Ausbau
   des `reference`-Scorers, Kit-Build-Aufgabenmodus.

Peer-Review (Lernende bewerten Lernende) bleibt ein **Phasen-/Workflow-Thema**
auf Snapshot+Annotation (siehe Roadmap), kein Scorer.

## Leitplanken

- Automatische Scores sind **immer Vorschläge** mit sichtbarer Begründung
  (Aufschlüsselung); die Note setzt die Lehrkraft (Übersicht: Validitäts-
  Einschränkungen jedes Einzelverfahrens).
- Kombination statt Einzelverfahren: Struktur-Metriken nur neben inhaltlichem
  Abgleich anzeigen (strukturell perfekter Unsinn).
- Jeder Scorer deklariert unterstützte Profile; die Registry filtert.
