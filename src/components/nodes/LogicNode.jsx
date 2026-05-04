// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const RELATION_ORDER = ['AND', 'OR', 'UNION', 'UNION ALL'];
const RELATION_TITLES = {
  AND: 'Posts must match ALL connected filters',
  OR: 'Posts can match ANY connected filter',
  UNION: 'Combines results from separate filter branches, removing duplicates',
  'UNION ALL': 'Combines results from separate filter branches, keeping all duplicates',
};

const LogicNode = ({ data, selected, id, onDelete, onUpdate }) => {
  const relation = data?.relation || 'AND';

  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete && window.confirm('Delete this Logic node?')) {
      onDelete(id);
    }
  };

  const handleCycleRelation = (e) => {
    e.stopPropagation();
    if (!onUpdate) return;
    const idx = RELATION_ORDER.indexOf(relation);
    const nextIdx = idx < 0 ? 0 : (idx + 1) % RELATION_ORDER.length;
    const nextRelation = RELATION_ORDER[nextIdx];
    onUpdate(id, { ...data, relation: nextRelation });
  };

  const unconnected = data?.unconnected === true;
  return (
    <div
      style={{
        padding: '10px 15px',
        background: selected ? '#ed8936' : '#2d3748',
        color: '#fff',
        borderRadius: '8px',
        minWidth: '150px',
        border: selected ? '2px solid #fff' : unconnected ? '1px dashed #718096' : '2px solid transparent',
        opacity: unconnected ? 0.7 : 1,
        position: 'relative',
      }}
      title={unconnected ? 'Not connected to output' : undefined}
    >
      {onDelete && (
        <button
          onClick={handleDelete}
          style={{
            position: 'absolute',
            top: '5px',
            right: '5px',
            background: 'rgba(255,0,0,0.7)',
            color: '#fff',
            border: 'none',
            borderRadius: '50%',
            width: '20px',
            height: '20px',
            cursor: 'pointer',
            fontSize: '12px',
            lineHeight: '1',
            padding: 0,
          }}
          title="Delete node"
        >
          ×
        </button>
      )}
      <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>
        Logic{unconnected && <span style={{ fontSize: '10px', opacity: 0.8, marginLeft: '4px' }}>(not in query)</span>}
      </div>
      <div
        onClick={handleCycleRelation}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleCycleRelation(e); } }}
        title={RELATION_TITLES[relation] || relation}
        style={{
          fontSize: '14px',
          textAlign: 'center',
          marginTop: '5px',
          padding: '4px 8px',
          border: '1px solid rgba(255,255,255,0.3)',
          borderRadius: '4px',
          cursor: onUpdate ? 'pointer' : 'default',
          textDecoration: 'underline',
          textUnderlineOffset: '2px',
        }}
      >
        {relation}
      </div>
      <Handle
        type="target"
        position={Position.Left}
        id="input"
        style={{ background: '#555', width: '10px', height: '10px', top: '50%' }}
      />
      <Handle
        type="source"
        position={Position.Right}
        style={{ background: '#555', width: '10px', height: '10px' }}
      />
    </div>
  );
};

export default LogicNode;
