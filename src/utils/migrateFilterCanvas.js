/**
 * Merge chained Filter→Filter nodes into one Filter with stacked conditions (pipeline AND semantics).
 * Merge parallel filters of the same field dimension into one Filter when they meet the same Logic node
 * (relation on the merged filter matches that Logic: AND/OR across branches).
 */

import {
  migrateFilterNodeDataToCanonical,
  isLegacyFilterNodeShape,
} from './normalizeFilterNode.js';

function newEdgeId(source, target) {
  return `reactflow__edge-${source}-${target}-${Math.random().toString(36).slice(2, 10)}`;
}

function dedupeEdgesByEndpoints(edgeList) {
  const seen = new Set();
  const out = [];
  for (const e of edgeList) {
    const k = `${e.source}\0${e.target}`;
    if (seen.has(k)) continue;
    seen.add(k);
    out.push(e);
  }
  return out;
}

function cloneConditionForMerge(c) {
  const row = {
    field: c.field != null ? String(c.field) : '',
    operator: c.operator != null ? String(c.operator) : '=',
    value: c.value,
    valueType: c.valueType || 'CHAR',
  };
  if (c.isCustomField) row.isCustomField = true;
  const op = String(row.operator || '').toUpperCase();
  if (op === 'BETWEEN' && c.value && typeof c.value === 'object' && !Array.isArray(c.value)) {
    row.value = {
      from: c.value.from !== undefined ? c.value.from : '',
      to: c.value.to !== undefined ? c.value.to : '',
    };
  }
  return row;
}

/**
 * Parallel-branch grouping key: same canonical field dimension (field string + custom-field flag).
 * Only filters with exactly one populated condition row participate — multi-condition nodes stay separate.
 *
 * @param {Object} filterNode
 * @returns {string|null}
 */
function parallelMergeGroupKey(filterNode) {
  if (!filterNode || filterNode.type !== 'filter') return null;
  const d = migrateFilterNodeDataToCanonical(filterNode.data);
  const nonEmpty = d.conditions.filter((c) => String(c.field || '').trim());
  if (nonEmpty.length !== 1) return null;
  const c = nonEmpty[0];
  const field = String(c.field);
  const cf = c.isCustomField ? '\x00cf' : '';
  return `${field}${cf}`;
}

/**
 * Collapse parallel Filter→Logic branches when several filters share mergeParallelGroupKey.
 *
 * @param {Array} nodes
 * @param {Array} edges
 * @returns {{ nodes: Array, edges: Array }}
 */
export function mergeParallelFilterGroupsAtLogic(nodes, edges) {
  let n = nodes.map((node) => ({ ...node }));
  let e = edges.map((edge) => ({ ...edge }));

  let changed = true;
  while (changed) {
    changed = false;
    const nodeById = new Map(n.map((node) => [node.id, node]));

    outer: for (const logic of n) {
      if (logic.type !== 'logic') continue;

      const logicRelation = logic.data?.relation === 'OR' ? 'OR' : 'AND';
      const incomingToLogic = e.filter((edge) => edge.target === logic.id);
      const predIds = [...new Set(incomingToLogic.map((edge) => edge.source))];
      const predFilters = predIds
        .map((id) => nodeById.get(id))
        .filter((node) => node && node.type === 'filter');

      if (predFilters.length < 2) continue;

      const groups = new Map();
      for (const f of predFilters) {
        const key = parallelMergeGroupKey(f);
        if (!key) continue;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(f.id);
      }

      for (const ids of groups.values()) {
        if (ids.length < 2) continue;

        const sortedIds = [...ids].sort();
        const survivorId = sortedIds[0];
        const removeIds = sortedIds.slice(1);
        const removeSet = new Set(removeIds);

        const survivor = nodeById.get(survivorId);
        if (!survivor) continue;

        const mergedConditions = [];
        let needsValidation = false;
        for (const id of sortedIds) {
          const node = nodeById.get(id);
          const d = migrateFilterNodeDataToCanonical(node?.data);
          if (node?.data && Object.prototype.hasOwnProperty.call(node.data, '_needsValidation')) {
            needsValidation = needsValidation || !!node.data._needsValidation;
          }
          const nonEmpty = d.conditions.filter((c) => String(c.field || '').trim());
          mergedConditions.push(...nonEmpty.map(cloneConditionForMerge));
        }

        const mergedData = {
          ...migrateFilterNodeDataToCanonical(survivor.data),
          label: survivor.data?.label || 'Filter',
          relation: logicRelation,
          conditions: mergedConditions,
        };
        if (needsValidation) mergedData._needsValidation = true;

        const nextEdges = [];
        for (const edge of e) {
          if (removeSet.has(edge.source)) continue;

          let target = edge.target;
          if (removeSet.has(target)) {
            target = survivorId;
          }

          nextEdges.push({
            ...edge,
            target,
            id: edge.target !== target ? newEdgeId(edge.source, target) : edge.id,
          });
        }

        let deduped = dedupeEdgesByEndpoints(nextEdges);
        if (!deduped.some((ed) => ed.source === survivorId && ed.target === logic.id)) {
          deduped.push({
            id: newEdgeId(survivorId, logic.id),
            source: survivorId,
            target: logic.id,
          });
          deduped = dedupeEdgesByEndpoints(deduped);
        }

        n = n
          .filter((node) => !removeSet.has(node.id))
          .map((node) => (node.id === survivorId ? { ...survivor, data: mergedData } : node));
        e = deduped;
        changed = true;
        break outer;
      }
    }
  }

  return { nodes: n, edges: e };
}

/**
 * Repeatedly merge filter B into filter A when an edge A→B exists and B has exactly one incoming edge.
 *
 * @param {Array} nodes
 * @param {Array} edges
 * @returns {{ nodes: Array, edges: Array }}
 */
export function mergeConsecutiveFilterChains(nodes, edges) {
  let n = nodes.map((node) => ({ ...node }));
  let e = edges.map((edge) => ({ ...edge }));

  let changed = true;
  while (changed) {
    changed = false;
    const nodeById = new Map(n.map((node) => [node.id, node]));
    const mergeEdge = e.find((edge) => {
      const src = nodeById.get(edge.source);
      const tgt = nodeById.get(edge.target);
      if (!src || !tgt || src.type !== 'filter' || tgt.type !== 'filter') {
        return false;
      }
      const incomingToTgt = e.filter((x) => x.target === tgt.id);
      return incomingToTgt.length === 1;
    });

    if (!mergeEdge) {
      break;
    }

    const sId = mergeEdge.source;
    const tId = mergeEdge.target;
    const src = nodeById.get(sId);
    const tgt = nodeById.get(tId);
    const d1 = migrateFilterNodeDataToCanonical(src.data);
    const d2 = migrateFilterNodeDataToCanonical(tgt.data);
    const conditions = [...d1.conditions, ...d2.conditions];
    const mergedData = {
      ...d1,
      label: src.data?.label || tgt.data?.label || 'Filter',
      relation: 'AND',
      conditions,
    };

    const outFromTgt = e.filter((x) => x.source === tId);
    let newEdges = e.filter((x) => x.source !== tId && x.target !== tId);

    for (const oe of outFromTgt) {
      if (newEdges.some((x) => x.source === sId && x.target === oe.target)) {
        continue;
      }
      newEdges.push({
        ...oe,
        id: newEdgeId(sId, oe.target),
        source: sId,
      });
    }

    n = n
      .filter((node) => node.id !== tId)
      .map((node) => (node.id === sId ? { ...src, data: mergedData } : node));
    e = newEdges;
    changed = true;
  }

  return { nodes: n, edges: e };
}

/**
 * Canonicalize legacy filter payloads, merge linear Filter→Filter chains (AND pipeline),
 * then merge parallel filters at Logic nodes that share the same field dimension (Logic relation preserved).
 *
 * @param {Array} nodes
 * @param {Array} edges
 * @returns {{ nodes: Array, edges: Array }}
 */
export function convertLegacyFiltersOnCanvas(nodes, edges) {
  const n = nodes.map((node) => {
    if (node.type !== 'filter' || !isLegacyFilterNodeShape(node.data)) {
      return node;
    }
    const data = migrateFilterNodeDataToCanonical(node.data);
    return { ...node, data };
  });
  const e = edges.map((edge) => ({ ...edge }));
  const chained = mergeConsecutiveFilterChains(n, e);
  return mergeParallelFilterGroupsAtLogic(chained.nodes, chained.edges);
}
