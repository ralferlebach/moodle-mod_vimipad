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

namespace mod_vimipad\local\service;

/**
 * Aggregates the operation log into edit-activity statistics for reporting.
 *
 * Reads the append-only {vimipad_operation} log (one row per applied edit, with
 * type, acting user and timestamp) and rolls it up per workspace, per user and
 * per operation type. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statistics_service {
    /**
     * Detailed edit statistics for a single workspace.
     *
     * @param int $workspaceid The workspace id.
     * @return array{total: int, bytype: array<string, int>, byuser: array<int, int>,
     *     contributors: int, firstactivity: int, lastactivity: int}
     */
    public function workspace_summary(int $workspaceid): array {
        global $DB;

        $totals = $DB->get_record_sql(
            'SELECT COUNT(*) AS total, MIN(timecreated) AS firstactivity, MAX(timecreated) AS lastactivity
               FROM {vimipad_operation}
              WHERE workspaceid = :wid',
            ['wid' => $workspaceid]
        );

        $bytype = [];
        $typerows = $DB->get_records_sql(
            'SELECT operationtype, COUNT(*) AS cnt
               FROM {vimipad_operation}
              WHERE workspaceid = :wid
           GROUP BY operationtype
           ORDER BY cnt DESC',
            ['wid' => $workspaceid]
        );
        foreach ($typerows as $row) {
            $bytype[(string) $row->operationtype] = (int) $row->cnt;
        }

        $byuser = [];
        $userrows = $DB->get_records_sql(
            'SELECT userid, COUNT(*) AS cnt
               FROM {vimipad_operation}
              WHERE workspaceid = :wid
           GROUP BY userid
           ORDER BY cnt DESC',
            ['wid' => $workspaceid]
        );
        foreach ($userrows as $row) {
            $byuser[(int) $row->userid] = (int) $row->cnt;
        }

        return [
            'total' => (int) $totals->total,
            'bytype' => $bytype,
            'byuser' => $byuser,
            'contributors' => count($byuser),
            'firstactivity' => (int) $totals->firstactivity,
            'lastactivity' => (int) $totals->lastactivity,
        ];
    }

    /**
     * Per-workspace roll-up for a whole activity instance.
     *
     * @param int $vimipadid The vimipad instance id.
     * @return array<int, array{workspaceid: int, userid: ?int, groupid: ?int,
     *     total: int, contributors: int, lastactivity: int}>
     */
    public function instance_overview(int $vimipadid): array {
        global $DB;

        $sql = "SELECT ws.id AS workspaceid,
                       ws.userid,
                       ws.groupid,
                       COUNT(o.id) AS total,
                       COUNT(DISTINCT o.userid) AS contributors,
                       COALESCE(MAX(o.timecreated), 0) AS lastactivity
                  FROM {vimipad_workspace} ws
             LEFT JOIN {vimipad_operation} o ON o.workspaceid = ws.id
                 WHERE ws.vimipadid = :vid
              GROUP BY ws.id, ws.userid, ws.groupid
              ORDER BY lastactivity DESC, ws.id ASC";

        $rows = [];
        foreach ($DB->get_records_sql($sql, ['vid' => $vimipadid]) as $row) {
            $rows[] = [
                'workspaceid' => (int) $row->workspaceid,
                'userid' => $row->userid !== null ? (int) $row->userid : null,
                'groupid' => $row->groupid !== null ? (int) $row->groupid : null,
                'total' => (int) $row->total,
                'contributors' => (int) $row->contributors,
                'lastactivity' => (int) $row->lastactivity,
            ];
        }

        return $rows;
    }
}
