// React is provided by WordPress via wp-element (available globally)
const { useState, useCallback, useEffect } = React;
import ReactFlow, {
  useNodesState,
  useEdgesState,
  addEdge,
  Background,
  Controls,
  MiniMap,
} from 'reactflow';
import 'reactflow/dist/style.css';

import SourceNode from './nodes/SourceNode';
import FilterNode from './nodes/FilterNode';
import SortNode from './nodes/SortNode';
import InclusionExclusionNode from './nodes/InclusionExclusionNode';
import LogicNode from './nodes/LogicNode';
import TargetNode from './nodes/TargetNode';
import NodeSettingsPanel from './NodeSettingsPanel';
import UpsellModal from './UpsellModal';
import { validateGraph } from '../utils/validator';
import { transformToSchema } from '../utils/schemaTransformer';

const QueryBuilderModal = ({ initialData, onSave, onClose }) => {
  // Parse initial data or create default nodes.
  const getInitialNodes = () => {
    if (initialData) {
      try {
        const parsed = typeof initialData === 'string' ? JSON.parse(initialData) : initialData;
        if (parsed.nodes && Array.isArray(parsed.nodes)) {
          return parsed.nodes;
        }
      } catch (e) {
        console.error('Failed to parse initial data:', e);
      }
    }
    // Default: Source + Target nodes.
    return [
      {
        id: 'source-1',
        type: 'source',
        data: { sourceType: 'post_type', postType: 'post', label: 'Source' },
        position: { x: 100, y: 200 },
      },
      {
        id: 'target-1',
        type: 'target',
        data: { postsPerPage: 10, orderBy: 'date', order: 'DESC', label: 'Query Output' },
        position: { x: 600, y: 200 },
      },
    ];
  };

  const getInitialEdges = () => {
    if (initialData) {
      try {
        const parsed = typeof initialData === 'string' ? JSON.parse(initialData) : initialData;
        if (parsed.edges && Array.isArray(parsed.edges)) {
          return parsed.edges;
        }
      } catch (e) {
        console.error('Failed to parse initial edges:', e);
      }
    }
    return [];
  };

  const [nodes, setNodes, onNodesChange] = useNodesState(getInitialNodes());
  const [edges, setEdges, onEdgesChange] = useEdgesState(getInitialEdges());
  const [selectedNode, setSelectedNode] = useState(null);
  const [showAddMenu, setShowAddMenu] = useState(false);
  const [addMenuPosition, setAddMenuPosition] = useState({ x: 0, y: 0 });
  const [validationErrors, setValidationErrors] = useState([]);
  const [savedQueries, setSavedQueries] = useState([]);
  const [showImportPanel, setShowImportPanel] = useState(false);
  const [upsellModal, setUpsellModal] = useState({ isOpen: false, featureName: '', description: '' });
  
  // Get PRO status from global config
  const isPro = window.QueryForgeConfig?.isPro || false;

  const onConnect = useCallback(
    (params) => setEdges((eds) => addEdge(params, eds)),
    [setEdges]
  );

  const onNodeClick = useCallback((event, node) => {
    event.stopPropagation();
    setSelectedNode(node);
  }, []);

  const onPaneClick = useCallback((event) => {
    // Don't close settings panel if clicking inside it
    if (event.target.closest('[data-qf-settings-panel]')) {
      return;
    }
    // Only close settings panel if clicking on the canvas background
    if (event.target.classList.contains('react-flow__pane')) {
      setSelectedNode(null);
      setShowAddMenu(false);
    }
  }, []);

  const onNodeDoubleClick = useCallback((event, node) => {
    event.stopPropagation();
    event.preventDefault();
    setSelectedNode(node);
  }, []);

  const handleAddNode = (type, position) => {
    // Join nodes are PRO-only - not available in Free version
    if (type === 'join') {
      return; // Do not allow Join nodes in Free version
    }
    // Free version: only one Source node per query
    if (type === 'source' && nodes.some(n => n.type === 'source')) {
      return;
    }

    const newNodeId = `${type}-${Date.now()}`;
    const defaultData = {
      source: { sourceType: 'post_type', postType: 'post', userRole: '', commentPostType: '', commentStatus: 'approve', label: 'Source' },
      filter: { field: '', operator: '=', value: '', valueType: 'CHAR', label: 'Filter' },
      sort: { field: 'date', direction: 'DESC', label: 'Sort' },
      inclusionExclusion: { postIn: '', postNotIn: '', authorIn: '', authorNotIn: '', ignoreStickyPosts: true, label: 'Include/Exclude' },
      logic: { relation: 'AND', label: 'Logic' },
      target: { postsPerPage: 10, orderBy: 'date', order: 'DESC', label: 'Query Output' },
    };

    const newNode = {
      id: newNodeId,
      type,
      data: defaultData[type] || { label: type },
      position: position || { x: 300, y: 200 },
    };

    setNodes((nds) => [...nds, newNode]);
    setShowAddMenu(false);
  };

  const handleContextMenu = useCallback((event) => {
    event.preventDefault();
    setAddMenuPosition({ x: event.clientX, y: event.clientY });
    setShowAddMenu(true);
  }, []);

  const handleUpdateNode = useCallback((nodeId, newData) => {
    setNodes((nds) =>
      nds.map((node) =>
        node.id === nodeId ? { ...node, data: { ...node.data, ...newData } } : node
      )
    );
  }, [setNodes]);

  const handleDeleteNode = useCallback((nodeId) => {
    setNodes((nds) => nds.filter((node) => node.id !== nodeId));
    setEdges((eds) => eds.filter((edge) => edge.source !== nodeId && edge.target !== nodeId));
    if (selectedNode?.id === nodeId) {
      setSelectedNode(null);
    }
  }, [setNodes, setEdges, selectedNode]);

  // Update sort order numbers when sort nodes or edges change.
  React.useEffect(() => {
    // Find all sort nodes connected to target (including chained Sort nodes).
    const targetNode = nodes.find(n => n.type === 'target');
    if (!targetNode) return;

    // Traverse backwards from Target to find all Sort nodes in the chain.
    // This handles both direct connections (Sort → Target) and chained connections (Sort → Sort → Target).
    const findSortNodesInChain = (targetId, visited = new Set()) => {
      const sortNodesInOrder = [];
      
      // Find all edges that connect TO this target
      const incomingEdges = edges.filter(e => e.target === targetId);
      
      for (const edge of incomingEdges) {
        const sourceNode = nodes.find(n => n.id === edge.source);
        
        if (sourceNode && sourceNode.type === 'sort') {
          // Found a Sort node connected to this target
          // Recursively find Sort nodes connected to this Sort node
          if (!visited.has(sourceNode.id)) {
            visited.add(sourceNode.id);
            const previousSortNodes = findSortNodesInChain(sourceNode.id, visited);
            // Add previous Sort nodes first (they come before this one)
            sortNodesInOrder.push(...previousSortNodes);
            // Then add this Sort node
            sortNodesInOrder.push(sourceNode);
          }
        }
      }
      
      return sortNodesInOrder;
    };

    // Get all Sort nodes in order (first in chain = index 0, last = highest index)
    const sortNodesInOrder = findSortNodesInChain(targetNode.id);

    // Update sort order on sort nodes.
    setNodes((nds) =>
      nds.map((node) => {
        if (node.type === 'sort') {
          const sortIndex = sortNodesInOrder.findIndex(sn => sn.id === node.id);
          return {
            ...node,
            data: {
              ...node.data,
              sortOrder: sortIndex >= 0 ? sortIndex + 1 : null,
            },
          };
        }
        return node;
      })
    );
  }, [edges, nodes.length]);

  // Create node types with delete handler
  const nodeTypes = React.useMemo(() => ({
    source: (props) => React.createElement(SourceNode, { ...props, onDelete: handleDeleteNode }),
    filter: (props) => React.createElement(FilterNode, { ...props, onDelete: handleDeleteNode }),
    sort: (props) => React.createElement(SortNode, { ...props, onDelete: handleDeleteNode }),
    inclusionExclusion: (props) => React.createElement(InclusionExclusionNode, { ...props, onDelete: handleDeleteNode }),
    logic: (props) => React.createElement(LogicNode, { ...props, onDelete: handleDeleteNode }),
    target: TargetNode,
  }), [handleDeleteNode]);

  const handleSave = () => {
    // Validate graph.
    const validation = validateGraph(nodes, edges);
    
    if (!validation.valid) {
      setValidationErrors(validation.errors);
      // Show toast/alert with better formatting.
      const errorMessage = 'Please fix the following issues:\n\n' + validation.errors.join('\n');
      alert(errorMessage);
      return;
    }

    setValidationErrors([]);

    // Transform to clean schema.
    const cleanSchema = transformToSchema(nodes, edges);

    // Save both formats.
    const graphState = JSON.stringify({ nodes, edges });
    const logicJson = JSON.stringify(cleanSchema);
    // Call onSave with both.
    onSave({ graphState, logicJson });
  };

  const handleSaveAs = () => {
    // Validate first.
    const validation = validateGraph(nodes, edges);
    if (!validation.valid) {
      const errorMessage = 'Please fix the following issues:\n\n' + validation.errors.join('\n');
      alert(errorMessage);
      return;
    }

    // Prompt for name.
    const queryName = prompt('Enter a name for this query:');
    if (!queryName || queryName.trim() === '') {
      return;
    }

    // Transform to clean schema.
    const cleanSchema = transformToSchema(nodes, edges);
    const graphState = JSON.stringify({ nodes, edges });
    const logicJson = JSON.stringify(cleanSchema);

    // Get display settings from widget (we'll need to pass this from parent).
    // For now, save query structure.
    const queryData = {
      name: queryName.trim(),
      date: new Date().toISOString(),
      graphState,
      logicJson,
      // displaySettings: {} // Will be added when we have access to widget settings
    };

    // Save via AJAX. Use only the URL and nonce from wp_localize_script (QueryForgeConfig).
    const ajaxUrl = window.QueryForgeConfig?.ajaxUrl;
    const nonce = window.QueryForgeConfig?.nonce || '';
    if (!ajaxUrl) {
      alert('Error: Query Forge configuration is missing. Please refresh the page.');
      return;
    }
    fetch(ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        action: 'query_forge_save_query',
        nonce,
        query_data: JSON.stringify(queryData),
      }),
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Query saved successfully!');
        } else {
          alert('Error saving query: ' + (data.data?.message || 'Unknown error'));
        }
      })
      .catch(error => {
        // Error handling is done via alert in the catch block
        alert('Error saving query. Please try again.');
      });
  };

  const handleImport = () => {
    const ajaxUrl = window.QueryForgeConfig?.ajaxUrl;
    const nonce = window.QueryForgeConfig?.nonce || '';
    if (!ajaxUrl) {
      alert('Error: Query Forge configuration is missing. Please refresh the page.');
      return;
    }
    fetch(ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        action: 'query_forge_get_saved_queries',
        nonce,
      }),
    })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data.queries) {
          // Convert object to array for easier iteration
          const queriesArray = Object.values(data.data.queries);
          setSavedQueries(queriesArray);
          setShowImportPanel(true);
        } else {
          alert('No saved queries found.');
        }
      })
      .catch(error => {
        alert('Error loading saved queries.');
      });
  };

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: 'rgba(0,0,0,0.85)',
        zIndex: 99999,
        display: 'flex',
      }}
    >
      <div
        style={{
          width: '95%',
          height: '95%',
          margin: 'auto',
          background: '#1e1e1e',
          borderRadius: '8px',
          overflow: 'hidden',
          display: 'flex',
          flexDirection: 'column',
          color: '#fff',
          position: 'relative',
        }}
        onContextMenu={handleContextMenu}
      >
        {/* Header */}
        <div
          style={{
            padding: '20px',
            borderBottom: '1px solid #333',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <h3 style={{ margin: 0 }}>Query Forge</h3>
          <div>
            <button
              onClick={(e) => {
                e.stopPropagation();
                if (!nodes.some(n => n.type === 'source')) {
                  handleAddNode('source', { x: 200 + (nodes.filter(n => n.type === 'source').length * 50), y: 150 + (nodes.filter(n => n.type === 'source').length * 100) });
                }
              }}
              style={{
                marginRight: '10px',
                background: nodes.some(n => n.type === 'source') ? '#1a202c' : '#2d3748',
                border: '1px solid #444',
                color: nodes.some(n => n.type === 'source') ? '#718096' : '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: nodes.some(n => n.type === 'source') ? 'not-allowed' : 'pointer',
                opacity: nodes.some(n => n.type === 'source') ? 0.7 : 1,
              }}
              title={nodes.some(n => n.type === 'source') ? 'One source per query in the free version. Need more? Go Pro.' : 'Add Source node'}
            >
              + Source
            </button>
            <button
              onClick={() => handleAddNode('filter', { x: 400, y: 150 })}
              style={{
                marginRight: '10px',
                background: '#2d3748',
                border: '1px solid #444',
                color: '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              + Filter
            </button>
            <button
              onClick={() => handleAddNode('sort', { x: 450, y: 150 })}
              style={{
                marginRight: '10px',
                background: '#2d3748',
                border: '1px solid #444',
                color: '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              + Sort
            </button>
            <button
              onClick={() => handleAddNode('inclusionExclusion', { x: 475, y: 150 })}
              style={{
                marginRight: '10px',
                background: '#2d3748',
                border: '1px solid #444',
                color: '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              + Include/Exclude
            </button>
            <button
              onClick={() => handleAddNode('logic', { x: 525, y: 150 })}
              style={{
                marginRight: '10px',
                background: '#2d3748',
                border: '1px solid #444',
                color: '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              + Logic
            </button>
            <button
              onClick={handleImport}
              style={{
                marginRight: '10px',
                background: '#2d3748',
                border: '1px solid #444',
                color: '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              Import
            </button>
            <button
              onClick={handleSaveAs}
              style={{
                marginRight: '10px',
                background: '#2d3748',
                border: '1px solid #444',
                color: '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              Save As
            </button>
            <button
              onClick={onClose}
              style={{
                marginRight: '10px',
                background: 'transparent',
                border: '1px solid #444',
                color: '#fff',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              Cancel
            </button>
            <button
              onClick={handleSave}
              style={{
                background: '#5c4bde',
                color: '#fff',
                padding: '8px 20px',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
              }}
            >
              Save Logic
            </button>
          </div>
        </div>

        {/* Canvas */}
        <div style={{ flex: 1, position: 'relative' }}>
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onNodeClick={onNodeClick}
            onPaneClick={onPaneClick}
            onNodeDoubleClick={onNodeDoubleClick}
            nodeTypes={nodeTypes}
            fitView
            deleteKeyCode={['Delete', 'Backspace']}
            onNodesDelete={(nodesToDelete) => {
              nodesToDelete.forEach((node) => {
                handleDeleteNode(node.id);
              });
            }}
            preventScrolling={false}
          >
            <Background color="#333" gap={16} />
            <Controls />
            <MiniMap />
          </ReactFlow>

          {/* Node Settings Panel */}
          {selectedNode && (
            <NodeSettingsPanel
              node={selectedNode}
              onUpdate={handleUpdateNode}
              onClose={() => setSelectedNode(null)}
              sourceNodes={nodes.filter(n => n.type === 'source')}
            />
          )}

          {/* Import Panel */}
          {showImportPanel && (
            <div
              style={{
                position: 'absolute',
                right: '20px',
                top: '20px',
                width: '400px',
                maxHeight: '80vh',
                background: '#1e1e1e',
                border: '1px solid #333',
                borderRadius: '8px',
                padding: '20px',
                color: '#fff',
                zIndex: 1000,
                overflowY: 'auto',
              }}
              onClick={(e) => e.stopPropagation()}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '15px' }}>
                <h3 style={{ margin: 0 }}>Import Saved Query</h3>
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    setShowImportPanel(false);
                  }}
                  style={{
                    background: 'transparent',
                    border: 'none',
                    color: '#fff',
                    cursor: 'pointer',
                    fontSize: '20px',
                    lineHeight: '1',
                    padding: '0',
                    width: '24px',
                    height: '24px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                  title="Close"
                >
                  ×
                </button>
              </div>
              {!savedQueries || savedQueries.length === 0 ? (
                <p>No saved queries found.</p>
              ) : (
                <div>
                  {savedQueries.map((query) => (
                    <div
                      key={query.id}
                      style={{
                        padding: '15px',
                        marginBottom: '10px',
                        background: '#2d3748',
                        borderRadius: '4px',
                        border: '1px solid #444',
                      }}
                    >
                      <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>{query.name}</div>
                      <div style={{ fontSize: '12px', color: '#999', marginBottom: '10px' }}>
                        {new Date(query.date).toLocaleDateString()}
                      </div>
                      <div style={{ display: 'flex', gap: '10px' }}>
                        <button
                          onClick={() => {
                            // Import query.
                            try {
                              // Get graphState and logicJson from saved query.
                              const graphState = query.graphState || '';
                              const logicJson = query.logicJson || '';
                              
                              if (!graphState && !logicJson) {
                                alert('Error: Saved query has no data to import.');
                                return;
                              }
                              
                              // Parse graphState to get nodes and edges.
                              let graphData = null;
                              if (graphState) {
                                try {
                                  graphData = typeof graphState === 'string' ? JSON.parse(graphState) : graphState;
                                } catch (e) {
                                  // Silently handle parse errors
                                }
                              }
                              
                              // Update nodes and edges if graphData is available.
                              // NOTE: We do NOT call onSave here - user must click "Save Logic" to persist changes.
                              if (graphData && graphData.nodes && graphData.edges) {
                                setNodes(graphData.nodes);
                                setEdges(graphData.edges);
                              }
                              
                              setShowImportPanel(false);
                              alert('Query imported successfully! Click "Save Logic" to apply changes.');
                            } catch (e) {
                              alert('Error importing query: ' + (e.message || 'Unknown error'));
                            }
                          }}
                          style={{
                            flex: 1,
                            padding: '8px',
                            background: '#5c4bde',
                            color: '#fff',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: 'pointer',
                          }}
                        >
                          Import
                        </button>
                        <button
                          onClick={() => {
                            if (confirm('Delete this saved query?')) {
                              const ajaxUrl = window.QueryForgeConfig?.ajaxUrl;
                              const nonce = window.QueryForgeConfig?.nonce || '';
                              if (!ajaxUrl) {
                                alert('Error: Query Forge configuration is missing. Please refresh the page.');
                                return;
                              }
                              fetch(ajaxUrl, {
                                method: 'POST',
                                headers: {
                                  'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                  action: 'query_forge_delete_query',
                                  nonce,
                                  query_id: query.id,
                                }),
                              })
                                .then(response => response.json())
                                .then(data => {
                                  if (data.success) {
                                    setSavedQueries(savedQueries.filter(q => q.id !== query.id));
                                  } else {
                                    alert('Error deleting query.');
                                  }
                                });
                            }
                          }}
                          style={{
                            padding: '8px 15px',
                            background: '#e53e3e',
                            color: '#fff',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: 'pointer',
                          }}
                        >
                          Delete
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Context Menu */}
          {showAddMenu && (
            <div
              style={{
                position: 'fixed',
                left: addMenuPosition.x,
                top: addMenuPosition.y,
                background: '#2d3748',
                border: '1px solid #444',
                borderRadius: '4px',
                padding: '10px',
                zIndex: 10000,
              }}
            >
              <button
                onClick={() => {
                  if (!nodes.some(n => n.type === 'source')) {
                    handleAddNode('source', { x: addMenuPosition.x - 400, y: addMenuPosition.y - 200 });
                  }
                }}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '8px',
                  background: 'transparent',
                  border: 'none',
                  color: nodes.some(n => n.type === 'source') ? '#718096' : '#fff',
                  cursor: nodes.some(n => n.type === 'source') ? 'not-allowed' : 'pointer',
                  textAlign: 'left',
                  opacity: nodes.some(n => n.type === 'source') ? 0.7 : 1,
                }}
                title={nodes.some(n => n.type === 'source') ? 'One source per query in the free version. Need more? Go Pro.' : 'Add Source node'}
              >
                Add Source Node
              </button>
              <button
                onClick={() => handleAddNode('filter', { x: addMenuPosition.x - 400, y: addMenuPosition.y - 200 })}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '8px',
                  background: 'transparent',
                  border: 'none',
                  color: '#fff',
                  cursor: 'pointer',
                  textAlign: 'left',
                }}
              >
                Add Filter Node
              </button>
              <button
                onClick={() => handleAddNode('sort', { x: addMenuPosition.x - 400, y: addMenuPosition.y - 200 })}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '8px',
                  background: 'transparent',
                  border: 'none',
                  color: '#fff',
                  cursor: 'pointer',
                  textAlign: 'left',
                }}
              >
                Add Sort Node
              </button>
              <button
                onClick={() => handleAddNode('inclusionExclusion', { x: addMenuPosition.x - 400, y: addMenuPosition.y - 200 })}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '8px',
                  background: 'transparent',
                  border: 'none',
                  color: '#fff',
                  cursor: 'pointer',
                  textAlign: 'left',
                }}
              >
                Add Include/Exclude Node
              </button>
              <button
                onClick={() => handleAddNode('logic', { x: addMenuPosition.x - 400, y: addMenuPosition.y - 200 })}
                style={{
                  display: 'block',
                  width: '100%',
                  padding: '8px',
                  background: 'transparent',
                  border: 'none',
                  color: '#fff',
                  cursor: 'pointer',
                  textAlign: 'left',
                }}
              >
                Add Logic Node
              </button>
            </div>
          )}
        </div>
        
        {/* Footer with Explore Pro link */}
        <div
          style={{
            padding: '15px 20px',
            borderTop: '1px solid #333',
            display: 'flex',
            justifyContent: 'flex-end',
            alignItems: 'center',
          }}
        >
          <a
            href="https://queryforgeplugin.com"
            target="_blank"
            rel="noopener noreferrer"
            style={{
              fontSize: '12px',
              color: '#999',
              textDecoration: 'none',
            }}
            onMouseEnter={(e) => e.target.style.color = '#5c4bde'}
            onMouseLeave={(e) => e.target.style.color = '#999'}
          >
            Need more power? Explore Pro →
          </a>
        </div>
      </div>
      
      {/* Upsell Modal */}
      <UpsellModal
        isOpen={upsellModal.isOpen}
        onClose={() => setUpsellModal({ isOpen: false, featureName: '', description: '' })}
        featureName={upsellModal.featureName}
        description={upsellModal.description}
      />
    </div>
  );
};

export default QueryBuilderModal;
