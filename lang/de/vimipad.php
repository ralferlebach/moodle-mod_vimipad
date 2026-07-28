<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Aktionen';
$string['add'] = 'Hinzufügen';
$string['addannotation'] = 'Annotation hinzufügen';
$string['ai:accept'] = 'Als Feedback übernehmen';
$string['ai:draft'] = 'KI-Entwurf';
$string['ai:draftaccepted'] = 'Feedback übernommen.';
$string['ai:draftgenerated'] = 'KI-Entwurf erzeugt. Bitte prüfen und bearbeiten.';
$string['ai:editaccept'] = 'Feedback bearbeiten und übernehmen';
$string['ai:generate'] = 'Entwurf erzeugen';
$string['ai:heading'] = 'KI-Feedback-Unterstützung';
$string['ai:intro'] = 'Erzeugen Sie einen Feedbackentwurf. Sie müssen ihn prüfen, bearbeiten und übernehmen, bevor er verwendet wird. An Lernende wird nichts automatisch gesendet.';
$string['ai:norelations'] = 'Die Map enthält noch keine Relationen.';
$string['ai:notes'] = 'Ihre Notizen für die KI (optional)';
$string['ai:policyrequired'] = 'Sie müssen die KI-Nutzungsrichtlinie akzeptieren, bevor Sie diese Funktion verwenden.';
$string['ai:promptformat'] = 'Schreiben Sie maximal 180 Wörter. Nennen Sie zwei Stärken, zwei konkrete Verbesserungen und einen nächsten Arbeitsschritt. Formulieren Sie konstruktiv, konkret und lernförderlich.';
$string['ai:promptintro'] = 'Sie unterstützen eine Lehrperson beim Verfassen individuellen Feedbacks zu einer Wissenslandkarte einer lernenden Person. Bewerten Sie nicht neu; formulieren Sie auf Basis der Lehrerentscheidung.';
$string['ai:promptmap'] = 'Die Map als kompakte Relationstabelle (Subjekt — Relation — Objekt):';
$string['ai:promptnohallucinate'] = 'Verwenden Sie ausschließlich die bereitgestellten Informationen. Erfinden Sie keine Fakten, Begriffe oder Relationen, die nicht in der Map enthalten sind.';
$string['ai:promptnotes'] = 'Notizen der Lehrperson:';
$string['ai:promptpoints'] = 'Die Lehrperson hat {$a->points} von {$a->max} Punkten vergeben.';
$string['ai:promptprofile'] = 'Diagrammprofil: {$a}';
$string['ai:prompttask'] = 'Aufgabenstellung:';
$string['aienabled'] = 'KI-Feedback-Unterstützung aktivieren';
$string['aienabled_help'] = 'Erlaubt Lehrenden, über das Moodle-KI-Subsystem einen Entwurf für elaboriertes Feedback zu erzeugen. Entwürfe werden niemals ohne ausdrückliche Prüfung und Freigabe durch Lehrende an Lernende gesendet. Erfordert einen konfigurierten KI-Provider und die Berechtigung „KI nutzen".';
$string['annotationadded'] = 'Annotation hinzugefügt.';
$string['annotations'] = 'Annotationen';
$string['annotationtext'] = 'Annotation';
$string['collaborationmode'] = 'Bearbeitungsmodus';
$string['collaborationmode_help'] = 'Einzelarbeit: eine Map pro Teilnehmer/in. Gruppe: eine Map pro Moodle-Gruppe. Kurs: eine gemeinsame Map für den gesamten Kurs.';
$string['completionsubmit'] = 'Snapshot-Abgabe erforderlich';
$string['completionsubmit_desc'] = 'Einen Snapshot zur Bewertung abgeben';
$string['completionsubmit_label'] = 'Lernende müssen einen Snapshot abgeben, um die Aktivität abzuschließen';
$string['defaultprofile'] = 'Diagrammprofil';
$string['defaultprofile_help'] = 'Das Diagrammprofil legt erlaubte Knoten- und Relationstypen, Strukturregeln und das Standard-Layout der Map fest.';
$string['editor:actions'] = 'Aktionen';
$string['editor:add'] = 'Hinzufügen';
$string['editor:addnode'] = 'Begriff hinzufügen';
$string['editor:addrelation'] = 'Relation hinzufügen';
$string['editor:beingedited'] = 'Wird bearbeitet';
$string['editor:cancel'] = 'Abbrechen';
$string['editor:canvasaria'] = 'Map-Zeichenfläche mit verschiebbaren Begriffen';
$string['editor:canvasplaceholder'] = 'Die grafische Zeichenfläche erscheint hier in einer späteren Version.';
$string['editor:canvasview'] = 'Zeichenfläche';
$string['editor:confirm'] = 'Übernehmen';
$string['editor:deleterelation'] = 'Relation löschen';
$string['editor:dir_both'] = 'Doppelpfeil';
$string['editor:dir_left'] = 'Pfeil zur Quelle';
$string['editor:dir_none'] = 'Kein Pfeil';
$string['editor:dir_right'] = 'Pfeil zum Ziel';
$string['editor:dragnodes'] = 'Ziehen Sie einen Begriff auf eine Subjekt- oder Objektzelle, um eine Relation umzuhängen';
$string['editor:fmt_bigger'] = 'Schrift größer';
$string['editor:fmt_bold'] = 'Fett';
$string['editor:fmt_delete'] = 'Knoten löschen';
$string['editor:fmt_duplicate'] = 'Knoten duplizieren';
$string['editor:fmt_ellipse'] = 'Ellipse';
$string['editor:fmt_fill'] = 'Füllfarbe';
$string['editor:fmt_font'] = 'Schriftart';
$string['editor:fmt_fontdefault'] = 'Standardschrift';
$string['editor:fmt_highlight'] = 'Hintergrundfarbe';
$string['editor:fmt_italic'] = 'Kursiv';
$string['editor:fmt_move'] = 'Verschieben';
$string['editor:fmt_rect'] = 'Rechteck';
$string['editor:fmt_reset'] = 'Formatierung zurücksetzen';
$string['editor:fmt_roundrect'] = 'Abgerundetes Rechteck';
$string['editor:fmt_shape'] = 'Form';
$string['editor:fmt_smaller'] = 'Schrift kleiner';
$string['editor:fmt_text'] = 'Text';
$string['editor:fmt_textcolor'] = 'Schriftfarbe';
$string['editor:fmt_toolbar'] = 'Knoten formatieren';
$string['editor:fmt_underline'] = 'Unterstrichen';
$string['editor:line_curved'] = 'Gekrümmte Linie';
$string['editor:line_orthogonal'] = 'Rechtwinklige Linie';
$string['editor:line_straight'] = 'Gerade Linie';
$string['editor:listview'] = 'Liste';
$string['editor:loading'] = 'Wird geladen…';
$string['editor:locked'] = 'Diese Map ist gesperrt und kann nicht mehr bearbeitet werden.';
$string['editor:nodelabel'] = 'Begriffsbezeichnung';
$string['editor:norelations'] = 'Noch keine Relationen. Fügen Sie Begriffe hinzu und verbinden Sie sie.';
$string['editor:object'] = 'Objekt';
$string['editor:relation'] = 'Relation';
$string['editor:relations'] = 'Relationen';
$string['editor:reledit'] = 'Relation bearbeiten';
$string['editor:retarget'] = 'Umhängen';
$string['editor:reverse'] = 'Relation umkehren';
$string['editor:revision'] = 'Revision';
$string['editor:subject'] = 'Subjekt';
$string['editor:submit'] = 'Zur Bewertung abgeben';
$string['editor:submitconfirm'] = 'Nach dem Abgeben wird die Map gesperrt und kann nicht mehr bearbeitet werden. Fortfahren?';
$string['editor:submitted'] = 'Zur Bewertung abgegeben. Die Map ist jetzt gesperrt.';
$string['editorloading'] = 'Der ViMi-Pad-Editor wird geladen…';
$string['editorplaceholder'] = 'Hier erscheint der ViMi-Pad-Editor. Der Editor ist in dieser frühen Entwicklungsversion noch nicht enthalten.';
$string['editorpreview'] = 'Editor-Vorschau';
$string['error:aifailed'] = 'Die KI-Anfrage konnte nicht abgeschlossen werden. Bitte später erneut versuchen.';
$string['error:aiunavailable'] = 'KI-Feedback ist nicht verfügbar. Prüfen Sie, ob ein Provider konfiguriert und KI aktiviert ist.';
$string['error:alreadysubmitted'] = 'Diese Map wurde bereits abgegeben.';
$string['error:nodenotfound'] = 'Der referenzierte Knoten wurde nicht gefunden.';
$string['error:nogroup'] = 'Sie sind in dieser Aktivität keiner Gruppe zugeordnet.';
$string['error:notingroup'] = 'Sie sind kein Mitglied der ausgewählten Gruppe.';
$string['error:notownworkspace'] = 'Sie dürfen nur Ihre eigene Map bearbeiten.';
$string['error:relationnotfound'] = 'Die referenzierte Relation wurde nicht gefunden.';
$string['error:revisionconflict'] = 'Ihre Änderungen basieren auf einem veralteten Stand. Bitte neu laden und erneut versuchen.';
$string['error:workspacelocked'] = 'Diese Map ist gesperrt und kann nicht mehr bearbeitet werden.';
$string['feedback'] = 'Feedback';
$string['grade'] = 'Bewertung';
$string['gradeoutof'] = 'Bewertung (von {$a})';
$string['gradesaved'] = 'Bewertung gespeichert.';
$string['gradetitle'] = 'Snapshot ansehen und bewerten';
$string['mode_course'] = 'Kurs-Map';
$string['mode_group'] = 'Gruppenarbeit';
$string['mode_individual'] = 'Einzelarbeit';
$string['modulename'] = 'ViMi Pad';
$string['modulename_help'] = 'ViMi Pad (Visuell Miteinander Lernen) ist eine Aktivität zur visuellen Wissenskonstruktion: Concept Maps, Mindmaps, Baumdiagramme, semantische Netze und Word Maps — einzeln oder in Gruppen, mit snapshot-basierter Bewertung und KI-gestütztem Lehrenden-Feedback.';
$string['modulenameplural'] = 'ViMi Pads';
$string['noaccess'] = 'Sie haben keine Berechtigung, in dieser Aktivität eine Map zu bearbeiten.';
$string['noinstances'] = 'In diesem Kurs gibt es keine ViMi-Pad-Aktivitäten.';
$string['nosubmissions'] = 'Es wurden noch keine Snapshots abgegeben.';
$string['participant'] = 'Teilnehmer/in';
$string['pluginadministration'] = 'ViMi-Pad-Administration';
$string['pluginname'] = 'ViMi Pad';
$string['privacy:metadata:core_ai'] = 'KI-Feedbackentwürfe werden über das Moodle-KI-Subsystem erzeugt, das eine datenminimierte Repräsentation der Map verarbeitet.';
$string['privacy:metadata:createdby'] = 'Die Person, die das Element erstellt hat.';
$string['privacy:metadata:modifiedby'] = 'Die Person, die das Element zuletzt geändert hat.';
$string['privacy:metadata:timecreated'] = 'Der Zeitpunkt der Erstellung.';
$string['privacy:metadata:timemodified'] = 'Der Zeitpunkt der letzten Änderung.';
$string['privacy:metadata:vimipad_aifeedback'] = 'KI-Feedbackentwürfe und freigegebenes Lehrenden-Feedback zu einem Snapshot.';
$string['privacy:metadata:vimipad_aifeedback:acceptedtext'] = 'Der von Lehrenden freigegebene Feedbacktext.';
$string['privacy:metadata:vimipad_aifeedback:drafttext'] = 'Der KI-generierte Feedbackentwurf.';
$string['privacy:metadata:vimipad_aifeedback:graderid'] = 'Die Lehrperson, die das Feedback erzeugt oder freigegeben hat.';
$string['privacy:metadata:vimipad_annotation'] = 'Annotationen zu einem Snapshot.';
$string['privacy:metadata:vimipad_annotation:commenttext'] = 'Der Annotationstext.';
$string['privacy:metadata:vimipad_annotation:userid'] = 'Die Person, die die Annotation verfasst hat.';
$string['privacy:metadata:vimipad_grade'] = 'Bewertungen und Gesamtfeedback für eine lernende Person.';
$string['privacy:metadata:vimipad_grade:feedback'] = 'Der Gesamtfeedback-Text.';
$string['privacy:metadata:vimipad_grade:grade'] = 'Die vergebene Bewertung.';
$string['privacy:metadata:vimipad_grade:grader'] = 'Die Person, die die Bewertung vergeben hat.';
$string['privacy:metadata:vimipad_grade:userid'] = 'Die bewertete Person.';
$string['privacy:metadata:vimipad_journalentry'] = 'Persönliche Journaleinträge während der Map-Erstellung.';
$string['privacy:metadata:vimipad_journalentry:entrytext'] = 'Der Inhalt des Journaleintrags.';
$string['privacy:metadata:vimipad_journalentry:userid'] = 'Die Autorin oder der Autor des Journaleintrags.';
$string['privacy:metadata:vimipad_layout'] = 'Knoten- und Ansichtspositionen eines Map-Layouts.';
$string['privacy:metadata:vimipad_lock'] = 'Kurzlebige Bearbeitungssperren halten fest, welcher Nutzer gerade ein Element bearbeitet, damit sich Mitarbeitende nicht gegenseitig überschreiben. Sperren laufen automatisch ab.';
$string['privacy:metadata:vimipad_lock:userid'] = 'Der Nutzer, der die Bearbeitungssperre aktuell hält.';
$string['privacy:metadata:vimipad_node'] = 'Innerhalb einer Map erstellte Knoten.';
$string['privacy:metadata:vimipad_node:content'] = 'Optionaler Rich-Content des Knotens.';
$string['privacy:metadata:vimipad_node:label'] = 'Die Knotenbeschriftung.';
$string['privacy:metadata:vimipad_operation'] = 'Das Protokoll der Bearbeitungsoperationen einer Map.';
$string['privacy:metadata:vimipad_operation:operationtype'] = 'Die Art der durchgeführten Operation.';
$string['privacy:metadata:vimipad_operation:userid'] = 'Die Person, die die Operation durchgeführt hat.';
$string['privacy:metadata:vimipad_relation'] = 'Innerhalb einer Map erstellte Relationen.';
$string['privacy:metadata:vimipad_relation:label'] = 'Die Relationsbeschriftung.';
$string['privacy:metadata:vimipad_snapshot'] = 'Abgegebene, unveränderliche Snapshots einer Map.';
$string['privacy:metadata:vimipad_snapshot:submittedby'] = 'Die Person, die den Snapshot abgegeben hat.';
$string['privacy:metadata:vimipad_workspace'] = 'Die bearbeitbare Map einer Person oder Gruppe.';
$string['privacy:metadata:vimipad_workspace:userid'] = 'Die Eigentümerin oder der Eigentümer einer individuellen Map.';
$string['privacy:path:workspace'] = 'Arbeitsbereich';
$string['profile_bubblemap'] = 'Bubble / Word Map';
$string['profile_conceptmap'] = 'Concept Map';
$string['profile_mindmap'] = 'Mindmap / Radial Map';
$string['profile_semanticnetwork'] = 'Semantisches Netz';
$string['profile_tree'] = 'Baumdiagramm';
$string['savegrade'] = 'Bewertung speichern';
$string['setting:collabheading'] = 'Zusammenarbeit';
$string['setting:collabheading_desc'] = 'Einstellungen für die Echtzeit-Zusammenarbeit: Abfrage von Änderungen, dynamische Intervalle, Element-Sperren und optionale Push-Benachrichtigungen.';
$string['setting:enableai'] = 'KI-Feedback aktivieren';
$string['setting:enableai_desc'] = 'Erlaubt Lehrenden, über das Moodle-KI-Subsystem KI-Feedbackentwürfe zu erzeugen. Erfordert einen konfigurierten KI-Provider. Kann auch je Aktivität deaktiviert werden.';
$string['setting:leasetimeout'] = 'Zeitlimit für Element-Sperre';
$string['setting:leasetimeout_desc'] = 'Wie lange eine Bearbeitungssperre auf einem Knoten oder einer Relation gehalten wird. Der bearbeitende Client erneuert sie automatisch; bei Verbindungsabbruch läuft die Sperre nach dieser Zeit ab.';
$string['setting:polladaptive'] = 'Dynamische Abfrage';
$string['setting:polladaptive_desc'] = 'Das Abfrageintervall automatisch verlängern, wenn Antworten langsam sind oder nichts Neues vorliegt, und wieder verkürzen, sobald Aktivität einsetzt.';
$string['setting:pollinterval'] = 'Standard-Abfrageintervall';
$string['setting:pollinterval_desc'] = 'Wie oft der Editor auf Änderungen von Mitarbeitenden prüft. Kleinere Werte wirken lebendiger, erhöhen aber die Serverlast.';
$string['setting:pollmax'] = 'Maximales Abfrageintervall';
$string['setting:pollmax_desc'] = 'Das längste Intervall, das die dynamische Abfrage verwendet.';
$string['setting:pollmin'] = 'Minimales Abfrageintervall';
$string['setting:pollmin_desc'] = 'Das kürzeste Intervall, das die dynamische Abfrage verwendet.';
$string['setting:pushenabled'] = 'Push-Benachrichtigungen aktivieren';
$string['setting:pushenabled_desc'] = 'Optional. Wenn aktiviert und ein Push-Endpunkt konfiguriert ist, erhält der Editor Änderungen in Echtzeit statt durch Abfrage. Erfordert einen separaten Push-Dienst; die Abfrage bleibt als Rückfallebene.';
$string['setting:pushendpoint'] = 'Push-Endpunkt-URL';
$string['setting:pushendpoint_desc'] = 'Die URL des Push-Dienstes, mit dem sich der Editor verbindet, wenn Push-Benachrichtigungen aktiviert sind.';
$string['setting:storeprompts'] = 'KI-Prompts speichern';
$string['setting:storeprompts_desc'] = 'Wenn aktiviert, wird der an die KI gesendete Prompt zu jedem Entwurf gespeichert (Transparenz/Nachvollziehbarkeit). Prompts sind datenminimiert und enthalten keine Namen von Lernenden.';
$string['snapshotstatus_0'] = 'Entwurf';
$string['snapshotstatus_1'] = 'Abgegeben';
$string['snapshotstatus_2'] = 'In Bewertung';
$string['snapshotstatus_3'] = 'Bewertet';
$string['snapshotstatus_4'] = 'Zurückgegeben';
$string['status'] = 'Status';
$string['submissions'] = 'Abgaben';
$string['submit'] = 'Zur Bewertung abgeben';
$string['submitconfirm'] = 'Nach der Abgabe ist die Map gesperrt und kann nicht mehr bearbeitet werden. Fortfahren?';
$string['submitted'] = 'Zur Bewertung abgegeben.';
$string['subplugintype_vimipadform'] = 'Darstellungsform';
$string['subplugintype_vimipadform_plural'] = 'Darstellungsformen';
$string['viewandgrade'] = 'Ansehen und bewerten';
$string['vimipad:addinstance'] = 'Neue ViMi-Pad-Aktivität hinzufügen';
$string['vimipad:comment'] = 'Maps und Snapshots kommentieren';
$string['vimipad:editgroup'] = 'Gruppen-Map bearbeiten';
$string['vimipad:editown'] = 'Eigene Map bearbeiten';
$string['vimipad:export'] = 'Maps und Snapshots exportieren';
$string['vimipad:grade'] = 'Abgegebene Snapshots bewerten';
$string['vimipad:manageprofiles'] = 'Diagrammprofile verwalten';
$string['vimipad:submit'] = 'Snapshot zur Bewertung abgeben';
$string['vimipad:useai'] = 'KI-Feedback-Unterstützung nutzen';
$string['vimipad:view'] = 'ViMi-Pad-Aktivität ansehen';
$string['vimipadname'] = 'Name der Aktivität';
