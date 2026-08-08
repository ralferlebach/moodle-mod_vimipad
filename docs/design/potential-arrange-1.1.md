# 1.1 — Rein-potenzialbasiertes Anordnen (experimenteller Umbau)

Status: **geplant für 1.1**, experimentell. Dieses Dokument hält die Zielarchitektur,
die Diagnose des heutigen Modells und die Umsetzungsschritte fest. Die vollständige
Herleitung mit Gradient in (x,y), Hessematrix und optimaler Schrittweite liegt als
gesetztes PDF vor (`docs/design/potentiale.pdf`, `potentiale-teil2.pdf`).

## Warum der Umbau

Das heutige Anordnen kombiniert vier Bausteine, die zusammen **keine globale
Kohärenz** garantieren und **driften**:

- **Knotenabstoßung** mit endlichem Träger (kräftefrei ab ρ ≥ 1) — keine Fernwirkung.
- **Kantentopf** (Smootherstep-Kern + Gauß-Auslauf), im Fernfeld **bewusst
  kräftefrei** — eine lange Kante zieht allein nicht zusammen; der nachgerüstete
  Hooke-Spring ist der einzige langreichweitige Zug, aber **nur entlang Kanten**.
- **Container-Fit** separat, *nicht im Gradienten* → einzige Quelle einer Nettokraft
  auf das Knotensystem ohne Reaktionspartner → Drift.
- **Schwerpunkt-Gravitation** als „Topf": hängt nur von `(x − Schwerpunkt(x))` ab →
  **translationsinvariant** → kann eine Netto-Translation der Wolke prinzipiell nicht
  verhindern; zudem verschwindend schwach.

Konsequenz (Feldbeobachtung): zwischen nicht verbundenen Knoten gibt es keine
Anziehung, und die ganze Wolke „wandert" pro Anordnen-Klick langsam über den Canvas,
bis die Container ihre Mitglieder umschließen.

## Zielmodell (rein potenzialbasiert)

1. **Stress-/MDS-Kern (Fernanziehung, Kohärenz):**
   `E_stress = Σ_{i<j} w_ij (‖x_i − x_j‖ − δ_ij)²`, `δ_ij = L · d_ij^graph`,
   `w_ij = δ_ij^−2`. Jedes Paar bekommt eine Zieldistanz → globale Kohärenz per
   Konstruktion, kein Zerfallen.
2. **Hooke-Verbinder** statt Topf: `½ k (r − r_0)²` — linearer Zug bei jeder Dehnung.
3. **Absolut verankertes Canvas-Grundpotential** (flach-innen/steil-außen) gegen Drift:
   Bulk-Anker `½ k_b ‖x̄ − C₀‖²` (uniformer Gradient, bremst nur Netto-Translation),
   leichte Innenzentrierung `½ ε ρ̃²`, steiler Rand `k_rim · ReLU(ρ̃ − R)²`,
   `ρ̃ = ‖x − C₀‖/L`. `C₀` ist ein **persistierter** Ankerpunkt (nicht der laufende
   Schwerpunkt): gesperrte Elemente definieren ihn, sonst der Start-Schwerpunkt.
4. **Container als vollwertige Potentialkörper im Gradienten** (statt Nachzieh-Fit),
   damit ihre Kraft eine Reaktion erhält und keine unphysikalische Nettokraft
   erzeugt.
5. **Garantiert absteigender Schritt** statt Armijo-Backtracking mit fester
   Schrittkappe: Majorisierung/SMACOF (Guttman-Transform, feste Schrittweite 1) für
   den Stress-Kern, bzw. die optimale Schrittweite `α* = ‖g‖²/(gᵀHg)` (ein
   Hesse-Vektor-Produkt). Das beseitigt das mehrfache „Wandern bis zur Endposition".

## Was erhalten bleibt

Die profilabhängigen Terme (Richtung, lineare Ordnung, zyklische Ordnung — und die
für 1.2 geplanten: 1D-Linien-Confinement für Timeline, zweigweise Richtung für
Fishbone, typisierte Kanten für Argumentbäume, Cluster-Anziehung für Affinity)
bleiben ein **Aufsatz** über der `LayoutPotentialProvider`-Contract (0.8.16). Der
Kern-Umbau berührt sie nicht; jedes Profil deklariert weiterhin deklarativ seine
Zusatzterme.

## Umsetzungsschritte (Skizze)

1. All-Paar-Shortestpaths (BFS je Knoten; für große Maps gekappt/gesampelt) → `δ_ij`.
2. Stress-Energie + SMACOF-Update als eigener Solver-Pfad, hinter einem Experiment-
   Flag (Admin-Setting), parallel zum bestehenden Kraftsolver.
3. Hooke-Verbinder + Canvas-Grundpotential + Container-im-Gradienten.
4. `C₀`-Persistenz (Layout-Kanal oder Workspace-Feld) inkl. Backup/Restore.
5. Drift-Regressionstest: mehrfaches Anordnen darf den Schwerpunkt nur um ε
   verschieben; Gradient-Tests (Finite-Differenzen) für jeden neuen Term;
   Kohärenz-Test (unverbundene Teilgraphen bleiben zusammen).
6. A/B gegen den heutigen Solver auf echten Maps; Kalibrierung.

## Offene Fragen

- Kosten der All-Paar-Distanzen bei 300–1000 Knoten (Sampling/Landmark-MDS?).
- Interaktion Stress-Kern ↔ Container-Potentiale ↔ Ordnungsterme (Gewichte).
- Ob SMACOF mit den nicht-Stress-Zusatztermen noch garantiert absteigt oder ein
  gemischter Schritt (SMACOF für Stress, Gradient für den Rest) nötig ist.
