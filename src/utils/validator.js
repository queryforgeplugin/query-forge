/**
 * Graph Validation Utility
 *
 * Validates React Flow graph structure before saving.
 */

/**
 * Validate graph structure
 *
 * @param {Array} nodes - React Flow nodes.
 * @param {Array} edges - React Flow edges.
 * @return {Object} { valid: boolean, errors: string[] }
 */
export function validateGraph(nodes, edges) {
  const errors = [];

  // Check for at least one SourceNode (multiple sources are allowed).
  const sourceNodes = nodes.filter(n => n.type === 'source');
  if (sourceNodes.length === 0) {
    errors.push('At least one Source node is required.');
  }

  // Check for exactly one TargetNode.
  const targetNodes = nodes.filter(n => n.type === 'target');
  if (targetNodes.length === 0) {
    errors.push('A Target (Output) node is required.');
  } else if (targetNodes.length > 1) {
    errors.push('Only one Target node is allowed.');
  }

  // Check connectivity: All FilterNodes, SortNodes, and InclusionExclusionNodes must connect to TargetNode (directly or via LogicNode).
  const filterNodes = nodes.filter(n => n.type === 'filter');
  const sortNodes = nodes.filter(n => n.type === 'sort');
  const inclusionExclusionNodes = nodes.filter(n => n.type === 'inclusionExclusion');
  const logicNodes = nodes.filter(n => n.type === 'logic');
  const targetNode = targetNodes[0];

  if (targetNode && (filterNodes.length > 0 || sortNodes.length > 0 || inclusionExclusionNodes.length > 0 || logicNodes.length > 0)) {
    // Only validate connectivity if there are filters, sorts, inclusion/exclusion, or logic nodes
    filterNodes.forEach(filterNode => {
      if (!isNodeConnectedToTarget(filterNode.id, edges, nodes, targetNode.id)) {
        errors.push(`Filter node "${filterNode.data?.label || filterNode.id}" is not connected to the Target node.`);
      }
    });

    sortNodes.forEach(sortNode => {
      if (!isNodeConnectedToTarget(sortNode.id, edges, nodes, targetNode.id)) {
        errors.push(`Sort node "${sortNode.data?.label || sortNode.id}" is not connected to the Target node.`);
      }
    });

    inclusionExclusionNodes.forEach(inclusionNode => {
      if (!isNodeConnectedToTarget(inclusionNode.id, edges, nodes, targetNode.id)) {
        errors.push(`Include/Exclude node "${inclusionNode.data?.label || inclusionNode.id}" is not connected to the Target node.`);
      }
    });

    // Check LogicNodes are connected to Target.
    logicNodes.forEach(logicNode => {
      if (!isNodeConnectedToTarget(logicNode.id, edges, nodes, targetNode.id)) {
        errors.push(`Logic node "${logicNode.id}" is not connected to the Target node.`);
      }
    });
  }

  // Check for orphan nodes (nodes with no connections) - but be lenient during editing.
  // Only check if we have filters/sorts/inclusion/exclusion/logic nodes that should be connected.
  if (filterNodes.length > 0 || sortNodes.length > 0 || inclusionExclusionNodes.length > 0 || logicNodes.length > 0) {
  const connectedNodeIds = new Set();
  edges.forEach(edge => {
    connectedNodeIds.add(edge.source);
    connectedNodeIds.add(edge.target);
  });

    // Source can be unconnected if no filters exist yet
    // Target should always be present (already checked above)
    filterNodes.forEach(filterNode => {
      if (!connectedNodeIds.has(filterNode.id)) {
        errors.push(`Filter node "${filterNode.data?.label || filterNode.id}" must be connected.`);
      }
    });

    sortNodes.forEach(sortNode => {
      if (!connectedNodeIds.has(sortNode.id)) {
        errors.push(`Sort node "${sortNode.data?.label || sortNode.id}" must be connected.`);
      }
    });

    inclusionExclusionNodes.forEach(inclusionNode => {
      if (!connectedNodeIds.has(inclusionNode.id)) {
        errors.push(`Include/Exclude node "${inclusionNode.data?.label || inclusionNode.id}" must be connected.`);
      }
    });

    logicNodes.forEach(logicNode => {
      if (!connectedNodeIds.has(logicNode.id)) {
        errors.push(`Logic node "${logicNode.id}" must be connected.`);
    }
  });
  }

  return {
    valid: errors.length === 0,
    errors
  };
}

/**
 * Check if a node is connected to the target (directly or via other nodes)
 *
 * @param {string} nodeId - Node ID to check.
 * @param {Array} edges - React Flow edges.
 * @param {Array} nodes - React Flow nodes.
 * @param {string} targetId - Target node ID.
 * @return {boolean}
 */
function isNodeConnectedToTarget(nodeId, edges, nodes, targetId) {
  // Direct connection.
  const directEdge = edges.find(e => e.source === nodeId && e.target === targetId);
  if (directEdge) {
    return true;
  }

  // Check if connected via LogicNode.
  const outgoingEdges = edges.filter(e => e.source === nodeId);
  for (const edge of outgoingEdges) {
    const targetNode = nodes.find(n => n.id === edge.target);
    if (targetNode && targetNode.type === 'logic') {
      // Check if LogicNode connects to Target.
      if (isNodeConnectedToTarget(edge.target, edges, nodes, targetId)) {
        return true;
      }
    }
    // Recursive check for other intermediate nodes.
    if (isNodeConnectedToTarget(edge.target, edges, nodes, targetId)) {
      return true;
    }
  }

  return false;
}

