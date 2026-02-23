/**
 * Schema Transformation Utility
 *
 * Transforms React Flow graph (nodes/edges) into clean JSON schema for PHP parser.
 */

/**
 * Transform graph to clean schema
 *
 * @param {Array} nodes - React Flow nodes.
 * @param {Array} edges - React Flow edges.
 * @return {Object} Clean schema object.
 */
export function transformToSchema(nodes, edges) {
  const schema = {
    version: '1.0',
    joins: [],
    filters: {
      relation: 'AND',
      clauses: []
    },
    target: {
      posts_per_page: 10,
      orderby: 'date',
      order: 'DESC'
    }
  };

  // Single source: one source node, one source object.
  const sourceNode = nodes.find(n => n.type === 'source');
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
      schema.source = sourceData;
    } else if (sourceType === 'comment') {
      sourceData.value = 'comment';
      if (sourceNode.data.commentPostType) sourceData.post_type = sourceNode.data.commentPostType;
      if (sourceNode.data.commentStatus) sourceData.status = sourceNode.data.commentStatus;
      schema.source = sourceData;
    } else if (sourceType === 'sql_table' && sourceNode.data.tableName) {
      sourceData.value = sourceNode.data.tableName;
      schema.source = sourceData;
    } else if (sourceType === 'rest_api' && sourceNode.data.apiUrl) {
      sourceData.value = sourceNode.data.apiUrl;
      sourceData.method = sourceNode.data.apiMethod || 'GET';
      schema.source = sourceData;
    }
    if (!schema.source) {
      schema.source = sourceData;
    }
  }

  // Find all JoinNodes and add them to joins array.
  const joinNodes = nodes.filter(n => n.type === 'join');
  joinNodes.forEach(joinNode => {
    if (joinNode && joinNode.data && joinNode.data.table) {
      const joinData = {
        table: joinNode.data.table,
        on: {
          left: joinNode.data.on?.left || 'ID',
          right: joinNode.data.on?.right || 'post_id',
        },
      };
      // Add alias if provided and different from table name
      if (joinNode.data.alias && joinNode.data.alias !== joinNode.data.table) {
        joinData.alias = joinNode.data.alias;
      }
      schema.joins.push(joinData);
    }
  });

  // Find all SortNodes connected to Target, ordered by connection.
  const targetNode = nodes.find(n => n.type === 'target');
  const sortNodes = [];
  
  if (targetNode) {
    // Get edges connecting sort nodes to target.
    const sortEdges = edges.filter(e => {
      const sourceNode = nodes.find(n => n.id === e.source);
      return sourceNode && sourceNode.type === 'sort' && e.target === targetNode.id;
    });

    // Sort edges by connection order (use edge id for ordering).
    sortEdges.sort((a, b) => a.id.localeCompare(b.id));

    // Extract sort nodes in order.
    sortEdges.forEach(edge => {
      const sortNode = nodes.find(n => n.id === edge.source && n.type === 'sort');
      if (sortNode && sortNode.data) {
        const sortData = {
          field: sortNode.data.field || 'date',
          direction: sortNode.data.direction || 'DESC',
        };
        // Include meta_key if sorting by meta value
        if ((sortNode.data.field === 'meta_value' || sortNode.data.field === 'meta_value_num') && sortNode.data.meta_key) {
          sortData.meta_key = sortNode.data.meta_key;
        }
        sortNodes.push(sortData);
      }
    });
  }

  // If we have sort nodes, use them. Otherwise, use target node defaults.
  if (sortNodes.length > 0) {
    // First sort node is primary.
    schema.target.orderby = sortNodes[0].field;
    schema.target.order = sortNodes[0].direction;
    
    // Store meta_key for primary sort if it's a meta_value sort
    if ((sortNodes[0].field === 'meta_value' || sortNodes[0].field === 'meta_value_num') && sortNodes[0].meta_key) {
      schema.target.meta_key = sortNodes[0].meta_key;
    }
    
    // Additional sorts stored in sorts array (for future use).
    if (sortNodes.length > 1) {
      schema.target.sorts = sortNodes.slice(1);
    }
  } else {
    // Fallback to TargetNode settings.
    if (targetNode && targetNode.data) {
    if (targetNode.data.orderBy) {
      schema.target.orderby = targetNode.data.orderBy;
    }
    if (targetNode.data.order) {
      schema.target.order = targetNode.data.order;
      }
    }
  }

  // Get other target settings.
  if (targetNode && targetNode.data) {
    if (targetNode.data.postsPerPage) {
      schema.target.posts_per_page = parseInt(targetNode.data.postsPerPage) || 10;
    }
  }

  // Build filter structure from nodes and edges.
  const filterStructure = buildFilterStructure(nodes, edges);
  if (filterStructure) {
    schema.filters = filterStructure;
  }

  // Find InclusionExclusion node and extract settings.
  const inclusionExclusionNode = nodes.find(n => n.type === 'inclusionExclusion');
  if (inclusionExclusionNode && inclusionExclusionNode.data) {
    schema.include_exclude = {};
    
    if (inclusionExclusionNode.data.postIn) {
      schema.include_exclude.post__in = inclusionExclusionNode.data.postIn;
    }
    if (inclusionExclusionNode.data.postNotIn) {
      schema.include_exclude.post__not_in = inclusionExclusionNode.data.postNotIn;
    }
    if (inclusionExclusionNode.data.authorIn) {
      schema.include_exclude.author__in = inclusionExclusionNode.data.authorIn;
    }
    if (inclusionExclusionNode.data.authorNotIn) {
      schema.include_exclude.author__not_in = inclusionExclusionNode.data.authorNotIn;
    }
    if (inclusionExclusionNode.data.ignoreStickyPosts !== undefined) {
      schema.include_exclude.ignore_sticky_posts = inclusionExclusionNode.data.ignoreStickyPosts;
    }
  }

  return schema;
}

/**
 * Build filter structure from graph
 *
 * @param {Array} nodes - React Flow nodes.
 * @param {Array} edges - React Flow edges.
 * @return {Object|null} Filter structure.
 */
function buildFilterStructure(nodes, edges) {
  const filterNodes = nodes.filter(n => n.type === 'filter');
  const logicNodes = nodes.filter(n => n.type === 'logic');
  const targetNode = nodes.find(n => n.type === 'target');

  if (!targetNode || filterNodes.length === 0) {
    return null;
  }

  // Recursive function to find all filter nodes connected to a given node (directly or through intermediate nodes).
  function findConnectedFilters(nodeId, visited = new Set()) {
    if (visited.has(nodeId)) {
      return [];
    }
    visited.add(nodeId);

    const connectedFilters = [];
    
    // Find all incoming edges to this node.
    const incomingEdges = edges.filter(e => e.target === nodeId);
    
    for (const edge of incomingEdges) {
      const sourceNode = nodes.find(n => n.id === edge.source);
      if (!sourceNode) {
        continue;
      }

      if (sourceNode.type === 'filter') {
        // Found a filter node!
        connectedFilters.push(sourceNode);
      } else if (sourceNode.type === 'logic') {
        // Recursively find filters connected to this logic node.
        const logicFilters = findConnectedFilters(sourceNode.id, visited);
        connectedFilters.push(...logicFilters);
      } else if (sourceNode.type === 'sort' || sourceNode.type === 'inclusionExclusion') {
        // Traverse through Sort or Include/Exclude nodes.
        const intermediateFilters = findConnectedFilters(sourceNode.id, visited);
        connectedFilters.push(...intermediateFilters);
      }
    }

    return connectedFilters;
  }

  // Find all filters connected to target (directly or through intermediate nodes).
  const connectedFilters = findConnectedFilters(targetNode.id);

  if (connectedFilters.length === 0) {
    return null;
  }

  // Check if we have LogicNodes in the path.
  const targetIncomingEdges = edges.filter(e => e.target === targetNode.id);
  const hasLogicNodes = targetIncomingEdges.some(e => {
    const sourceNode = nodes.find(n => n.id === e.source);
    return sourceNode && sourceNode.type === 'logic';
  });

  if (hasLogicNodes) {
  // Complex case: LogicNodes involved.
  const logicEdgesToTarget = targetIncomingEdges.filter(e => {
    const sourceNode = nodes.find(n => n.id === e.source);
    return sourceNode && sourceNode.type === 'logic';
  });

  if (logicEdgesToTarget.length > 0) {
    // Build structure from LogicNodes.
    const clauses = logicEdgesToTarget.map(edge => {
      const logicNode = nodes.find(n => n.id === edge.source);
      return buildLogicGroup(logicNode, nodes, edges);
    }).filter(Boolean);

    return {
        relation: 'AND',
      clauses
    };
  }
  }

  // Simple case: filters directly or through intermediate nodes (no LogicNodes at target level).
  return {
    relation: 'AND',
    clauses: connectedFilters.map(filterNode => buildFilterClause(filterNode)).filter(Boolean)
  };
}

/**
 * Build filter clause from FilterNode
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
 * Build logic group from LogicNode
 *
 * @param {Object} logicNode - Logic node.
 * @param {Array} nodes - All nodes.
 * @param {Array} edges - All edges.
 * @return {Object|null} Logic group.
 */
function buildLogicGroup(logicNode, nodes, edges) {
  if (!logicNode || !logicNode.data) {
    return null;
  }

  // Find incoming edges to this LogicNode.
  const incomingEdges = edges.filter(e => e.target === logicNode.id);
  
  const clauses = incomingEdges.map(edge => {
    const sourceNode = nodes.find(n => n.id === edge.source);
    if (sourceNode && sourceNode.type === 'filter') {
      return buildFilterClause(sourceNode);
    }
    return null;
  }).filter(Boolean);

  if (clauses.length === 0) {
    return null;
  }

  return {
    relation: logicNode.data.relation || 'AND',
    clauses
  };
}

