/**
 * Schema Transformation Utility
 *
 * Transforms React Flow graph (nodes/edges) into clean JSON schema via a
 * recursive forward-walking DAG traverser. No pattern matching; topology is
 * always derived from the graph.
 */

/**
 * Build filter clause from FilterNode (unchanged).
 *
 * @param {Object} filterNode - Filter node.
 * @return {Object|null} Filter clause.
 */
function buildFilterClause(filterNode) {
  if (!filterNode || !filterNode.data) {
    return null;
  }
  const clause = {
    field: filterNode.data.field || '',
    operator: filterNode.data.operator || '=',
  };
  if (filterNode.data.value !== undefined && filterNode.data.value !== '') {
    clause.value = filterNode.data.value;
  }
  if (filterNode.data.valueType) {
    clause.value_type = filterNode.data.valueType;
  }
  return clause;
}

/**
 * Transform graph to clean schema. Forward walk from Source; commit to schema only when path reaches Output.
 *
 * @param {Array} nodes - React Flow nodes.
 * @param {Array} edges - React Flow edges.
 * @return {{ schema: Object, unconnectedNodeIds: string[] }}
 */
export function transformToSchema(nodes, edges) {
  const unconnectedNodeIds = [];
  const visited = new Set();

  const getNode = (id) => nodes.find((n) => n.id === id);
  const getOutgoing = (id) => edges.filter((e) => e.source === id);
  const getIncoming = (id) => edges.filter((e) => e.target === id);

  const sourceNode = nodes.find((n) => n.type === 'source');
  const outputNode = nodes.find((n) => n.type === 'target');
  const outputId = outputNode ? outputNode.id : null;

  const schema = {
    version: '1.0',
    joins: [],
    target: {
      posts_per_page: 10,
      orderby: 'date',
      order: 'DESC',
    },
  };

  // --- Source (keep existing logic) ---
  if (sourceNode && sourceNode.data) {
    let sourceType = sourceNode.data.sourceType || 'post_type';
    if (sourceType === 'posts' || sourceType === 'pages' || sourceType === 'cpts') {
      sourceType = 'post_type';
    }
    const sourceData = { type: sourceType };
    if (sourceType === 'post_type') {
      if (sourceNode.data.sourceType === 'posts') {
        sourceData.value = 'post';
      } else if (sourceNode.data.sourceType === 'pages') {
        sourceData.value = 'page';
      } else if (sourceNode.data.sourceType === 'cpts' && sourceNode.data.postType) {
        sourceData.value = sourceNode.data.postType;
      } else if (sourceNode.data.postType) {
        sourceData.value = sourceNode.data.postType;
      } else {
        sourceData.value = 'post';
      }
    } else if (sourceType === 'user') {
      sourceData.value = 'user';
      if (sourceNode.data.userRole) sourceData.role = sourceNode.data.userRole;
    } else if (sourceType === 'comment') {
      sourceData.value = 'comment';
      if (sourceNode.data.commentPostType) sourceData.post_type = sourceNode.data.commentPostType;
      if (sourceNode.data.commentStatus) sourceData.status = sourceNode.data.commentStatus;
    } else if (sourceType === 'sql_table' && sourceNode.data.tableName) {
      sourceData.value = sourceNode.data.tableName;
    } else if (sourceType === 'rest_api' && sourceNode.data.apiUrl) {
      sourceData.value = sourceNode.data.apiUrl;
      sourceData.method = sourceNode.data.apiMethod || 'GET';
    }
    schema.source = sourceData;
  }

  // --- Joins (keep existing) ---
  nodes.filter((n) => n.type === 'join').forEach((joinNode) => {
    if (joinNode?.data?.table) {
      const joinData = {
        table: joinNode.data.table,
        on: {
          left: joinNode.data.on?.left || 'ID',
          right: joinNode.data.on?.right || 'post_id',
        },
      };
      if (joinNode.data.alias && joinNode.data.alias !== joinNode.data.table) {
        joinData.alias = joinNode.data.alias;
      }
      schema.joins.push(joinData);
    }
  });

  // --- Sort (keep existing: collect from sort nodes connected to target) ---
  if (outputNode) {
    const sortEdges = edges.filter((e) => {
      const src = getNode(e.source);
      return src?.type === 'sort' && e.target === outputNode.id;
    });
    sortEdges.sort((a, b) => (a.id || '').localeCompare(b.id || ''));
    const sortNodes = sortEdges.map((e) => getNode(e.source)).filter(Boolean);
    if (sortNodes.length > 0) {
      schema.target.orderby = sortNodes[0].data?.field || 'date';
      schema.target.order = sortNodes[0].data?.direction || 'DESC';
      if (['meta_value', 'meta_value_num'].includes(sortNodes[0].data?.field) && sortNodes[0].data?.meta_key) {
        schema.target.meta_key = sortNodes[0].data.meta_key;
      }
      if (sortNodes.length > 1) {
        schema.target.sorts = sortNodes.slice(1).map((sn) => ({
          field: sn.data?.field || 'date',
          direction: sn.data?.direction || 'DESC',
          ...(sn.data?.meta_key && { meta_key: sn.data.meta_key }),
        }));
      }
    } else if (outputNode.data) {
      if (outputNode.data.orderBy) schema.target.orderby = outputNode.data.orderBy;
      if (outputNode.data.order) schema.target.order = outputNode.data.order;
    }
    if (outputNode.data?.postsPerPage) {
      schema.target.posts_per_page = parseInt(outputNode.data.postsPerPage, 10) || 10;
    }
  }

  // --- Include/Exclude (keep existing) ---
  const incExNode = nodes.find((n) => n.type === 'inclusionExclusion');
  if (incExNode?.data) {
    schema.include_exclude = {};
    if (incExNode.data.postIn) schema.include_exclude.post__in = incExNode.data.postIn;
    if (incExNode.data.postNotIn) schema.include_exclude.post__not_in = incExNode.data.postNotIn;
    if (incExNode.data.authorIn) schema.include_exclude.author__in = incExNode.data.authorIn;
    if (incExNode.data.authorNotIn) schema.include_exclude.author__not_in = incExNode.data.authorNotIn;
    if (incExNode.data.ignoreStickyPosts !== undefined) {
      schema.include_exclude.ignore_sticky_posts = incExNode.data.ignoreStickyPosts;
    }
  }

  if (!sourceNode || !outputId) {
    schema.query = null;
    return { schema, unconnectedNodeIds };
  }

  // Fix 1 — Floating nodes (no connections):
  nodes.forEach((node) => {
    if (node.type === 'source' || node.type === 'target') return;
    if (unconnectedNodeIds.includes(node.id)) return;
    const hasAnyEdge = edges.some((e) => e.source === node.id || e.target === node.id);
    if (!hasAnyEdge) {
      unconnectedNodeIds.push(node.id);
    }
  });

  // Fix 2 — Connected but empty filter nodes (no field and no value):
  // Note: ' ' (space) counts as a valid user-intended value.
  nodes.forEach((node) => {
    if (node.type !== 'filter') return;
    if (unconnectedNodeIds.includes(node.id)) return;
    const data = node.data || {};
    const hasField = !!data.field;
    const hasValue = data.value !== undefined && data.value !== '';
    if (!hasField && !hasValue) {
      unconnectedNodeIds.push(node.id);
    }
  });

  // Reachability: if Source cannot reach any Target node at all, mark no_output and bail.
  const seen = new Set();
  const stack = [sourceNode.id];
  let reachesOutput = false;
  while (stack.length && !reachesOutput) {
    const id = stack.pop();
    if (seen.has(id)) continue;
    seen.add(id);
    getOutgoing(id).forEach((e) => {
      const targetNode = getNode(e.target);
      if (targetNode?.type === 'target') reachesOutput = true;
      else if (!seen.has(e.target)) stack.push(e.target);
    });
  }
  if (!reachesOutput) {
    schema.no_output = true;
    return { schema, unconnectedNodeIds };
  }

  // --- Forward walk: build query, collect unconnected ---
  function collectUnconnected(nodeId) {
    if (!nodeId || nodeId === outputId || unconnectedNodeIds.includes(nodeId)) return;
    unconnectedNodeIds.push(nodeId);
    const node = getNode(nodeId);
    if (!node) return;
    getOutgoing(nodeId).forEach((e) => collectUnconnected(e.target));
  }

  /** Skip past Sort node(s). First Sort is pass-through; consecutive Sorts are collected as unconnected. Returns { nextId, nextNode, unconnectedSorts }. */
  function skipSorts(sortNodeId) {
    const unconnectedSorts = [];
    let currentId = sortNodeId;
    while (currentId) {
      const node = getNode(currentId);
      if (!node || node.type !== 'sort') {
        return { nextId: currentId, nextNode: node, unconnectedSorts };
      }
      const out = getOutgoing(currentId);
      if (out.length === 0) {
        return { nextId: null, nextNode: null, unconnectedSorts };
      }
      const nextId = out[0].target;
      const nextNode = getNode(nextId);
      if (nextNode?.type === 'sort') {
        unconnectedSorts.push(nextId);
        currentId = nextId;
        continue;
      }
      return { nextId, nextNode, unconnectedSorts };
    }
    return { nextId: null, nextNode: null, unconnectedSorts };
  }

  /** endNodeIds: stop when we hit any of these (e.g. [logicId] or [outputId]) */
  function getBranchFragment(nodeId, endNodeIds, seen) {
    const stopAt = new Set(Array.isArray(endNodeIds) ? endNodeIds : [endNodeIds]);
    const acc = { steps: [], unconnected: [] };
    let currentId = nodeId;
    const stepVisited = new Set(seen);

    while (currentId && !stopAt.has(currentId)) {
      if (stepVisited.has(currentId)) break;
      stepVisited.add(currentId);
      const node = getNode(currentId);
      if (!node) break;
      if (node.type === 'filter') {
        const clause = buildFilterClause(node);
        if (clause) acc.steps.push({ filter: clause });
      } else if (node.type === 'inclusionExclusion' && node.data) {
        const ie = {};
        if (node.data.postIn) ie.post__in = node.data.postIn;
        if (node.data.postNotIn) ie.post__not_in = node.data.postNotIn;
        if (node.data.authorIn) ie.author__in = node.data.authorIn;
        if (node.data.authorNotIn) ie.author__not_in = node.data.authorNotIn;
        if (node.data.ignoreStickyPosts !== undefined) ie.ignore_sticky_posts = node.data.ignoreStickyPosts;
        if (Object.keys(ie).length) acc.steps.push({ include_exclude: ie });
      } else if (node.type === 'sort') {
        const out = getOutgoing(currentId);
        if (out.length === 0) {
          acc.unconnected.push(currentId);
          break;
        }
        let nextId = out[0].target;
        let nextNode = getNode(nextId);
        while (nextNode?.type === 'sort') {
          acc.unconnected.push(nextId);
          const nextOut = getOutgoing(nextId);
          if (nextOut.length === 0) break;
          nextId = nextOut[0].target;
          nextNode = getNode(nextId);
        }
        if (nextNode?.type === 'sort') break;
        currentId = nextId;
        continue;
      } else {
        acc.unconnected.push(currentId);
        break;
      }
      const out = getOutgoing(currentId);
      if (out.length === 0) {
        acc.unconnected.push(currentId);
        break;
      }
      currentId = out[0].target;
    }

    if (acc.unconnected.length) return { fragment: null, unconnected: acc.unconnected };
    if (acc.steps.length === 0) return { fragment: null, unconnected: [] };
    if (acc.steps.length === 1) return { fragment: acc.steps[0], unconnected: [] };
    return { fragment: { pipeline: acc.steps }, unconnected: [] };
  }

  function getLogicQuery(logicNodeId) {
    const logicNode = getNode(logicNodeId);
    if (!logicNode || logicNode.type !== 'logic') return { query: null, unconnected: [] };
    const out = getOutgoing(logicNodeId);
    const goesToOutput = out.some((e) => e.target === outputId);

    if (goesToOutput) {
      const relation = logicNode.data?.relation || 'AND';
      const incoming = getIncoming(logicNodeId);
      const branches = [];
      const unc = [];
      incoming.forEach((e) => {
        const { fragment, unconnected } = getBranchFragment(e.source, [logicNodeId], new Set());
        if (fragment) branches.push(fragment);
        unc.push(...unconnected);
      });
      if (branches.length === 0) return { query: null, unconnected: [logicNodeId, ...unc] };
      return { query: { logic: { relation, branches } }, unconnected: unc };
    }

    // Logic in the middle (single or multi input): one outgoing path to non-Output.
    if (out.length === 0) return { query: null, unconnected: [logicNodeId] };
    let nextId = out[0].target;
    let nextNode = getNode(nextId);
    const unconnectedSorts = [];
    if (nextNode?.type === 'sort') {
      const skipped = skipSorts(nextId);
      nextId = skipped.nextId;
      nextNode = skipped.nextNode;
      unconnectedSorts.push(...(skipped.unconnectedSorts || []));
    }
    if (!nextId) return { query: null, unconnected: [logicNodeId, ...unconnectedSorts] };
    // Logic → Sort → Logic and direct Logic → Logic are not allowed.
    if (nextNode?.type === 'logic') {
      return { query: null, unconnected: [logicNodeId, nextId, ...unconnectedSorts] };
    }
    const relation = logicNode.data?.relation || 'AND';
    const res = getQueryFrom(nextId);
    if (res.query) {
      return {
        query: { logic: { relation, branches: [res.query] } },
        unconnected: [...(res.unconnected || []), ...unconnectedSorts],
      };
    }
    return { query: null, unconnected: [logicNodeId, ...(res.unconnected || []), ...unconnectedSorts] };
  }

  function getQueryFrom(nodeId) {
    if (nodeId === outputId) return { query: null, unconnected: [] };
    if (visited.has(nodeId)) return { query: null, unconnected: [] };
    visited.add(nodeId);
    const node = getNode(nodeId);
    if (!node) return { query: null, unconnected: [nodeId] };

    if (node.type === 'filter') {
      const clause = buildFilterClause(node);
      if (!clause) return { query: null, unconnected: [nodeId] };
      const out = getOutgoing(nodeId);
      if (out.length === 0) {
        return { query: null, unconnected: [nodeId] };
      }

      // If multiple outgoing edges exist, prefer an edge that can reach the output
      // (directly or via sort nodes). This prevents incorrectly marking a node
      // as unconnected when a valid output path exists.
      const edgeToUse =
        out.find((e) => {
          let candidateId = e.target;
          let candidateNode = getNode(candidateId);
          if (candidateNode?.type === 'sort') {
            const skipped = skipSorts(candidateId);
            candidateId = skipped.nextId;
            candidateNode = skipped.nextNode;
          }
          return (
            candidateId &&
            (candidateId === outputId || candidateNode?.type === 'target')
          );
        }) || out[0];

      let nextId = edgeToUse.target;
      let nextNode = getNode(nextId);
      let unconnectedSorts = [];
      if (nextNode?.type === 'sort') {
        const skipped = skipSorts(nextId);
        nextId = skipped.nextId;
        nextNode = skipped.nextNode;
        unconnectedSorts = skipped.unconnectedSorts || [];
      }
      if (!nextId) {
        return { query: null, unconnected: [nodeId, ...unconnectedSorts] };
      }
      if (nextId === outputId || nextNode?.type === 'target') {
        return { query: { filter: clause }, unconnected: unconnectedSorts };
      }
      if (nextNode?.type === 'logic') {
        const res = getLogicQuery(nextId);
        visited.add(nextId);
        if (res.query) {
          const steps = res.query.pipeline ? [{ filter: clause }, ...res.query.pipeline] : [{ filter: clause }, res.query];
          return {
            query: { pipeline: steps },
            unconnected: [...(res.unconnected || []), ...unconnectedSorts],
          };
        }
        return { query: null, unconnected: [nodeId, ...(res.unconnected || []), ...unconnectedSorts] };
      }
      if (nextNode?.type === 'filter') {
        const res = getQueryFrom(nextId);
        if (res.query && res.unconnected.length === 0) {
          const rest = res.query.pipeline ? res.query.pipeline : (res.query.filter ? [res.query] : []);
          return { query: { pipeline: [{ filter: clause }, ...rest] }, unconnected: unconnectedSorts };
        }
        return { query: null, unconnected: [nodeId, ...res.unconnected, ...unconnectedSorts] };
      }
      if (nextNode?.type === 'inclusionExclusion') {
        const res = getQueryFrom(nextId);
        if (res.query && res.unconnected.length === 0) {
          const step = { filter: clause };
          const rest = res.query.pipeline ? res.query.pipeline : (res.query.filter ? [res.query] : []);
          return { query: { pipeline: [step, ...rest] }, unconnected: unconnectedSorts };
        }
        return { query: null, unconnected: [nodeId, ...res.unconnected, ...unconnectedSorts] };
      }
      return { query: null, unconnected: [nodeId, ...unconnectedSorts] };
    }

    if (node.type === 'logic') {
      return getLogicQuery(nodeId);
    }

    if (node.type === 'inclusionExclusion' && node.data) {
      const ie = {};
      if (node.data.postIn) ie.post__in = node.data.postIn;
      if (node.data.postNotIn) ie.post__not_in = node.data.postNotIn;
      if (node.data.authorIn) ie.author__in = node.data.authorIn;
      if (node.data.authorNotIn) ie.author__not_in = node.data.authorNotIn;
      if (node.data.ignoreStickyPosts !== undefined) ie.ignore_sticky_posts = node.data.ignoreStickyPosts;
      const out = getOutgoing(nodeId);
      if (out.length === 0) return { query: null, unconnected: [nodeId] };
      let nextId = out[0].target;
      let nextNode = getNode(nextId);
      let unconnectedSorts = [];
      if (nextNode?.type === 'sort') {
        const skipped = skipSorts(nextId);
        nextId = skipped.nextId;
        unconnectedSorts = skipped.unconnectedSorts || [];
      }
      if (!nextId) {
        return { query: null, unconnected: [nodeId, ...unconnectedSorts] };
      }
      if (nextId === outputId || nextNode?.type === 'target') {
        if (Object.keys(ie).length) {
          const step = { include_exclude: ie };
          return { query: { pipeline: [step] }, unconnected: unconnectedSorts };
        }
        return { query: null, unconnected: unconnectedSorts };
      }
      const res = getQueryFrom(nextId);
      if (res.query && Object.keys(ie).length) {
        const step = { include_exclude: ie };
        const rest = res.query.pipeline ? res.query.pipeline : [res.query];
        return { query: { pipeline: [step, ...rest] }, unconnected: unconnectedSorts };
      }
      return { query: null, unconnected: [nodeId, ...(res.unconnected || []), ...unconnectedSorts] };
    }

    return { query: null, unconnected: [nodeId] };
  }

  const queryRoots = getOutgoing(sourceNode.id)
    .map((e) => e.target)
    .filter((id) => {
      const n = getNode(id);
      return n && ['filter', 'logic', 'inclusionExclusion'].includes(n.type);
    });

  if (queryRoots.length === 0) {
    schema.query = null;
  } else if (queryRoots.length === 1) {
    const res = getQueryFrom(queryRoots[0]);
    schema.query = res.query || null;
    res.unconnected.forEach((id) => collectUnconnected(id));
  } else {
    const first = getQueryFrom(queryRoots[0]);
    first.unconnected.forEach((id) => collectUnconnected(id));
    if (first.query?.logic) {
      schema.query = first.query;
    } else {
      const branches = [];
      if (first.query) branches.push(first.query);
      for (let i = 1; i < queryRoots.length; i++) {
        const res = getQueryFrom(queryRoots[i]);
        if (res.query) branches.push(res.query);
        res.unconnected.forEach((id) => collectUnconnected(id));
      }
      schema.query = branches.length > 1 ? { paths: branches } : (branches[0] || null);
    }
  }

  // Fix 1 — Floating nodes (no edges at all).
  nodes.forEach((node) => {
    if (node.type === 'source' || node.type === 'target') return;
    if (unconnectedNodeIds.includes(node.id)) return;
    const hasAnyEdge = edges.some((e) => e.source === node.id || e.target === node.id);
    if (!hasAnyEdge) {
      unconnectedNodeIds.push(node.id);
    }
  });
  return { schema, unconnectedNodeIds };
}
