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

import {operationToAction} from '../src/collab/apply_remote';

describe('operationToAction', () => {
    test('translates node_create into addNode', () => {
        const action = operationToAction({
            revision: 1, userid: 1, operationtype: 'node_create',
            payloadjson: JSON.stringify({stableid: 'n1', type: 'concept', label: 'Energy'}),
        });
        expect(action).toEqual({kind: 'addNode', node: {stableid: 'n1', type: 'concept', label: 'Energy'}});
    });

    test('translates node_delete into deleteNode', () => {
        const action = operationToAction({
            revision: 2, userid: 1, operationtype: 'node_delete',
            payloadjson: JSON.stringify({stableid: 'n1'}),
        });
        expect(action).toEqual({kind: 'deleteNode', stableid: 'n1'});
    });

    test('translates relation_create into addRelation', () => {
        const action = operationToAction({
            revision: 3, userid: 1, operationtype: 'relation_create',
            payloadjson: JSON.stringify({
                stableid: 'r1', sourceid: 'n1', targetid: 'n2',
                type: 'related', label: 'leads to', direction: 1,
            }),
        });
        expect(action).toEqual({
            kind: 'addRelation',
            relation: {
                stableid: 'r1', sourceid: 'n1', targetid: 'n2',
                type: 'related', label: 'leads to', direction: 1,
            },
        });
    });

    test('translates relation_delete into deleteRelation', () => {
        const action = operationToAction({
            revision: 4, userid: 1, operationtype: 'relation_delete',
            payloadjson: JSON.stringify({stableid: 'r1'}),
        });
        expect(action).toEqual({kind: 'deleteRelation', stableid: 'r1'});
    });

    test('translates relation_retarget into retargetRelation', () => {
        const action = operationToAction({
            revision: 5, userid: 1, operationtype: 'relation_retarget',
            payloadjson: JSON.stringify({stableid: 'r1', newsource: 'n3'}),
        });
        expect(action).toEqual({kind: 'retargetRelation', stableid: 'r1', sourceid: 'n3'});
    });

    test('returns null for unknown or layout-only operations', () => {
        expect(operationToAction({
            revision: 6, userid: 1, operationtype: 'node_update',
            payloadjson: JSON.stringify({stableid: 'n1', label: 'x'}),
        })).not.toBeNull(); // node_update maps to a label change
        expect(operationToAction({
            revision: 7, userid: 1, operationtype: 'totally_unknown',
            payloadjson: '{}',
        })).toBeNull();
    });

    test('tolerates malformed payload json by returning null', () => {
        expect(operationToAction({
            revision: 8, userid: 1, operationtype: 'node_create', payloadjson: 'not json',
        })).toBeNull();
    });
});
