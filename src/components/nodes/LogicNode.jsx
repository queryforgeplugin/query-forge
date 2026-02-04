// React is provided by WordPress via wp-element (available globally)
import { Handle, Position } from 'reactflow';

const LogicNode = ({ data, selected, id, onDelete }) => {
  const relation = data?.relation || 'AND';
  
  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete && window.confirm('Delete this Logic node?')) {
      onDelete(id);
    }
  };

  return (
    <div
      style={{
        padding: '10px 15px',
        background: selected ? '#ed8936' : '#2d3748',
        color: '#fff',
        borderRadius: '8px',
        minWidth: '150px',
        border: selected ? '2px solid #fff' : '2px solid transparent',
        position: 'relative',
      }}
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
      <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>Logic</div>
      <div style={{ fontSize: '14px', textAlign: 'center', marginTop: '5px' }}>
        {relation}
      </div>
      {/* Dynamic input handles - React Flow will allow multiple connections */}
      <Handle
        type="target"
        position={Position.Left}
        id="input-1"
        style={{ background: '#555', width: '10px', height: '10px', top: '25%' }}
      />
      <Handle
        type="target"
        position={Position.Left}
        id="input-2"
        style={{ background: '#555', width: '10px', height: '10px', top: '50%' }}
      />
      <Handle
        type="target"
        position={Position.Left}
        id="input-3"
        style={{ background: '#555', width: '10px', height: '10px', top: '75%' }}
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

